<?php

namespace Dcol\Tweet;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

use App\Exceptions\PostTweetFailedException,
    App\Exceptions\XApiRateLimitedException,
    App\Models\Blog,
    App\Models\BlogPost,
    App\Models\Document,
    Dcol\AbstractManager,
    Dcol\Blog\Post\Manager as BlogPostManager,
    Dcol\WordPress\Posts,
    Dcol\WordPress\Auth\WordPressAuthInterface,
    Dcol\WordPress\Request\WordPressRequest,
    Dcol\X\Request\XRequest,
    Dcol\X\Tweets;

class Manager extends AbstractManager
{

    const FILE_EXT='json';

    const RATE_LIMIT_RESET_KEY='x-rate-limit-reset';
    const RATE_LIMIT_24_HOUR_RESET_KEY='x-app-limit-24hour-reset';

    /**
     * Instance of a Blog model.
     *
     * @var Blog
     */
    protected $blog;

    /**
     * Instance of a BlogPost model.
     *
     * @var BlogPost
     */
    protected $blogPost;
    
    /**
     * Oauth1 instance X Api.
     *
     * @var Oauth1
     */
    protected $xAuth;

    /**
     * X API Tweets implementation.
     *
     * @var Tweets
     */
    protected $tweetsApi;

    /**
     * Constructor.
     *
     * @param BlogPost $blogPost
     */
    public function __construct(BlogPost $blogPost, string $baseCacheDir)
    {
        $this->setBlogPost($blogPost);
        $this->setTweetsApi(new Tweets($this->getXAuth()));
        $this->setCacheDir($baseCacheDir);
        $this->setFileExtension(Manager::FILE_EXT);
    }

    /**
     * Creates a tweet for the Blog Post.
     *
     * @return bool
     */
    public function makeTweet(): bool
    {
        $blogPost = $this->getBlogPost();
    
        $body = ['text' => sprintf("%s %s",
            $this->removePartialSentences($blogPost->document->content->tweet),
            $blogPost->url
        )];
        
        try {
            $response = $this->getTweetsApi()->post($body);
        } catch(ClientException $e) {
            $response = $e->getResponse();
            $code = $response->getStatusCode();
            $responseBodyAsString = $response->getBody()->getContents();
            $responseHeaders = $response->getHeaders();
        }
        
        $code = $response->getStatusCode();

        if ($code === 429) {
            $resetTime = $this->cacheRateLimitHeaders($responseHeaders);
            throw new XApiRateLimitedException(sprintf("Rate Limit Exceeded in the X Api.\nRate Limit will be reset at: %s\n",
                $resetTime
            ));
        } else if ($code !== 201) {
            throw new PostTweetFailedException(sprintf(
                "Tweets POST request failed! Response: %s \"%s\"",
                (string)$code, 
                $response->getReasonPhrase()
            ));
        } else {
            $responseBody = $response->getBody();
            $data = json_decode($responseBody, true)['data'];
            if (isset($data['id'])) {
                return true;
            } else {
                throw new PostTweetFailedException("Malformed Tweet POST response: $responseBody");
            }
        }
        
        return false;
    }

    /**
     * Returns true/false if the API is currently rate limited.
     *
     * @return boolean
     */
    public function isRateLimited(): bool
    {
        $now = time();

        $reset = $this->getResetTs(self::RATE_LIMIT_RESET_KEY);
        $dailyReset = $this->getResetTs(self::RATE_LIMIT_24_HOUR_RESET_KEY);

        # If there is no cache return false.
        if (false === $reset && false === $dailyReset) {
            return false;
        }

        if (false === $reset) {
            $reset = $now;
        }

        if (false === $dailyReset) {
            $dailyReset = $now;
        }

        // Take the bigger of the two reset numbers.
        $reset = ($dailyReset > $reset) ? $dailyReset : $reset;

        # If now is less than the reset, we are rate limited.
        return ($now < $reset) ? true : false;
    }

    /**
     * Returns a time of when the Rate Limit period will be over.
     *
     * @return string|null
     */
    public function rateLimitResetTime(): string|null
    {
        $now = time();
        $reset = $this->getResetTs(self::RATE_LIMIT_RESET_KEY);
        $dailyReset = $this->getResetTs(self::RATE_LIMIT_24_HOUR_RESET_KEY);

        if (false === $reset) {
            $reset = $now;
        }

        if (false === $dailyReset) {
            $dailyReset = $now;
        }

        // Take the bigger of the two reset numbers.
        $reset = ($dailyReset > $reset) ? $dailyReset : $reset;

        $resetTime = new \DateTime();
        $resetTime->setTimestamp($reset);
        return $resetTime->format('D, d M Y H:i');
    }

    /**
     * Returns a unix time stamp given the key of a timestamp cache
     *
     * @param string $key
     * @return integer|false
     */
    protected function getResetTs(string $key): int|false
    {
        $cache = $this->getCache($key);

        if (false === $cache) {
            return false;
        }

        $data = json_decode($cache, true);
        return $data[$key];
    }

    /**
     * Handles caching of rate limiting strings from response headers.
     *
     * @param array $headers
     * @return string
     */
    protected function cacheRateLimitHeaders(array $headers): string
    {
        $reset = time();
        $dailyReset = time();
        
        if (isset($headers[self::RATE_LIMIT_RESET_KEY]) && isset($headers[self::RATE_LIMIT_RESET_KEY][0])) {
            (int)$reset = $headers[self::RATE_LIMIT_RESET_KEY][0];
        }

        if (isset($headers[self::RATE_LIMIT_24_HOUR_RESET_KEY]) && isset($headers[self::RATE_LIMIT_24_HOUR_RESET_KEY][0])) {
            (int)$dailyReset = $headers[self::RATE_LIMIT_24_HOUR_RESET_KEY][0];
        }

        $this->setCache(json_encode([self::RATE_LIMIT_RESET_KEY => $reset]), self::RATE_LIMIT_RESET_KEY);
        $this->setCache(json_encode([self::RATE_LIMIT_24_HOUR_RESET_KEY => $dailyReset]), self::RATE_LIMIT_24_HOUR_RESET_KEY);

        return $this->rateLimitResetTime();
    }

    /**
     * Attemps to remove what appears to be a partial sentence from the end of a string.
     *
     * @param string $text
     * @return string
     */
    protected function removePartialSentences(string $text): string 
    {
        $totalLength = strlen($text);
        $lastPeriod = strrpos($text, '.');
        $lengthAfterLastPeriod = $totalLength - $lastPeriod;

        # If the number of characters between the last period
        # and the total length is less than total length
        # Remove everything after the last period.
        if ($lengthAfterLastPeriod < $totalLength) {
            $text = substr($text, 0, ($lastPeriod + 1));
        }

        return trim($text);
    }

    /**
     * Get instance of a Blog model
     *
     * @return  Blog
     */ 
    public function getBlog(): Blog
    {
        if (null === $this->blog) {
            $this->blog = $this->getBlogPost()->blog;
        }

        return $this->blog;
    }

    /**
     * Set instance of a Blog model
     *
     * @param  Blog  $blog  Instance of a Blog model
     *
     * @return  self
     */ 
    public function setBlog(Blog $blog): Manager
    {
        $this->blog = $blog;

        return $this;
    }

    /**
     * Get instance of a BlogPost model.
     *
     * @return  BlogPost
     */ 
    public function getBlogPost(): BlogPost
    {
        return $this->blogPost;
    }

    /**
     * Set instance of a BlogPost model.
     *
     * @param  BlogPost  $blogPost  Instance of a BlogPost model.
     *
     * @return  self
     */ 
    public function setBlogPost(BlogPost $blogPost): Manager
    {
        $this->blogPost = $blogPost;

        return $this;
    }

    /**
     * Get oauth1 instance X Api.
     *
     * @return  Oauth1
     */ 
    public function getXAuth(): Oauth1
    {
        if (null === $this->xAuth) {
            $blog = $this->getBlog();
            $this->xAuth = new Oauth1([
                'consumer_key'    => $blog->x_api_key,
                'consumer_secret' => $blog->x_api_secret,
                'token'           => $blog->x_access_token,
                'token_secret'    => $blog->x_access_token_secret
            ]);
        }

        return $this->xAuth;
    }

    /**
     * Set oauth1 instance X Api.
     *
     * @param  Oauth1  $xAuth  Oauth1 instance X Api.
     *
     * @return  self
     */ 
    public function setXAuth(Oauth1 $xAuth): Manager
    {
        $this->xAuth = $xAuth;

        return $this;
    }

    /**
     * Get x API Tweets implementation.
     *
     * @return  Tweets
     */ 
    public function getTweetsApi(): Tweets
    {
        return $this->tweetsApi;
    }

    /**
     * Set x API Tweets implementation.
     *
     * @param  Tweets  $tweetsApi  X API Tweets implementation.
     *
     * @return  self
     */ 
    public function setTweetsApi(Tweets $tweetsApi): Manager
    {
        $this->tweetsApi = $tweetsApi;

        return $this;
    }

    /**
     * Get directory for holding cached files.
     *
     * @return  string
     */ 
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Set directory for holding cached files.
     *
     * @param  string  $cacheDir  Directory for holding cached files.
     *
     * @return  self
     */ 
    public function setCacheDir(string $dir): AbstractManager
    {
        if (!is_dir($dir)) {
            $this->buildDirFromUri($dir);
        }

        $this->cacheDir = $dir;

        return $this;
    }
}
