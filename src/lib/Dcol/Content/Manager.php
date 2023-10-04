<?php

namespace Dcol\Content;

use Illuminate\Http\Client\Response;

use Dcol\AbstractManager,
    Dcol\Assistant\OpenAi\Tokenizer,
    Dcol\Assistant\OpenAi\ChatCompletion,
    Dcol\Assistant\Request\OpenAiRequest;

class Manager extends AbstractManager
{
    const MAX_TOKENS_PER_MESSAGE = 2000;

    const FILE_EXT='txt';

    const TYPE_TITLE = 'title';

    const TYPE_BLURB = 'blurb';

    const TYPE_PUBLICATION_DATE = 'publication_date';

    const TYPE_TWEET = 'tweet';

    const TYPE_AUTHORS = 'authors';

    const TYPE_TAGS = 'tags';

    const TYPE_WRITEUP = 'writeup';

    const TYPE_HTML_WRITEUP = 'html_writeup';

    const TYPE_SUMMARY = 'summary';

    const TYPE_RAW = 'raw';

    const TYPE_FOCUS_KEYPHRASE = 'focus_keyphrase';

    const TYPE_META_DESCRIPTION = 'meta_description';

    const PROMPT_TITLE = 'Don\'t use quotes, using only 4 of the most important keywords write a title for this:';

    const PROMPT_BLURB = 'Don\'t use quotes, write a 250 character summary of this:';

    const PROMPT_PUBLICATION_DATE = 'Don\'t write anything but the date of publication, in this strict format YYYY-MM-DD:';

    const PROMPT_TWEET = 'Only use 255 characters, create a tweet for this:';

    const PROMPT_AUTHORS = 'Make a list of only the authors\' names, list one name per author, in this format: name, name, name';

    const PROMPT_TAGS = 'Select important terms as keywords that represent the most important parts of this text, limit the number of terms to a total of 6, only answer in this format keyword 1, keyword 2, keyword 3, keyword 4, keyword 5, keyword 6:';

    const PROMPT_WRITEUP = 'Use reported speech, don\'t create any titles or headings, use double newlines to create simple paragraphs, write a draft about this:';

    const PROMPT_SUMMARY = 'Don\'t create any titles or headings, Summarize this:';

    const PROMPT_FOCUS_KEYPHRASE = 'Using only 4 of the most important keywords reduce this text to a single sentence:';

    const PROMPT_META_DESCRIPTION = 'Encapsulate the meaning of this into a summary of only 155 characters including spaces:';

    /**
     * OpenAI chatcompletion implementation.
     *
     * @var ChatCompletion
     */
    protected $chat;

    /**
     * Associative array for holding the content values.
     *
     * @var array
     */
    protected $content = [];

    /**
     * List of prompts by content type.
     *
     * @var array
     */
    protected $prompts;

    /**
     * Constructor.
     *
     * @param ChatCompletion $chat
     * @param string $cacheDir
     * @param string $tmpDir
     * @param string $uri
     */
    public function __construct(ChatCompletion $chat, string $baseCacheDir, string $baseTmpDir, string $uri)
    {
        $this->setChat($chat);
        $this->setUri($uri);
        $this->setCacheDir($baseCacheDir);
        $this->setTmpDir($baseTmpDir);
        $this->setFileExtension(Manager::FILE_EXT);
        $this->getPrompts();
    }

    /**
     * Create the content data.
     *
     * @param string $text
     * @return array
     */
    public function createContent(string $text): array
    {
        # If the text is empty bail out.
        if ($text == '') {
            throw new \Exception("Text for \"{$this->uri}\" was empty");
        }

        # Cache Raw
        $this->setCache($text, self::TYPE_RAW);

        # writeup
        $this->content[self::TYPE_WRITEUP] = $this->filterWriteup($this->createContentType($text, self::TYPE_WRITEUP, null, true));
        $this->setCache($this->content[self::TYPE_WRITEUP], self::TYPE_WRITEUP);

        # title
        $this->content[self::TYPE_TITLE] = $this->filterTitle($this->createContentType($this->content[self::TYPE_WRITEUP], self::TYPE_TITLE));
        $this->setCache($this->content[self::TYPE_TITLE], self::TYPE_TITLE);

        # html_writeup
        $this->content[self::TYPE_HTML_WRITEUP] = $this->fiterHtml($this->content[self::TYPE_WRITEUP], self::TYPE_HTML_WRITEUP);
        $this->setCache($this->content[self::TYPE_HTML_WRITEUP], self::TYPE_HTML_WRITEUP);

        # publication_date
        $this->content[self::TYPE_PUBLICATION_DATE] = $this->filterPublicationDate($this->createContentType($text, self::TYPE_PUBLICATION_DATE));
        $this->setCache($this->content[self::TYPE_PUBLICATION_DATE], self::TYPE_PUBLICATION_DATE);

        # authors
        $authors = $this->createContentType($text, self::TYPE_AUTHORS);
        $this->setCache($authors, self::TYPE_AUTHORS);
        $this->content[self::TYPE_AUTHORS] = $this->filterAuthors(explode(',', $authors));

        # blurb
        $this->content[self::TYPE_BLURB] = $this->filterUnicode($this->createContentType($this->content[self::TYPE_WRITEUP], self::TYPE_BLURB));
        $this->setCache($this->content[self::TYPE_BLURB], self::TYPE_BLURB);

        # tweet
        $this->content[self::TYPE_TWEET] = $this->filterTweet($this->createContentType($this->content[self::TYPE_WRITEUP], self::TYPE_TWEET));
        $this->setCache($this->content[self::TYPE_TWEET], self::TYPE_TWEET);

        #tags
        $tags = $this->createContentType($this->content[self::TYPE_WRITEUP], self::TYPE_TAGS);
        $this->setCache($tags, self::TYPE_TAGS);
        $this->content[self::TYPE_TAGS] = $this->filterTags(explode(',', $tags));

        # summary
        $this->content[self::TYPE_SUMMARY] = $this->filterUnicode($this->createContentType($this->content[self::TYPE_WRITEUP], self::TYPE_SUMMARY));
        $this->setCache($this->content[self::TYPE_SUMMARY], self::TYPE_SUMMARY);

        # meta_description
        $this->content[self::TYPE_META_DESCRIPTION] = $this->filterMetaDescription($this->createContentType($this->content[self::TYPE_WRITEUP], self::TYPE_META_DESCRIPTION));
        $this->setCache($this->content[self::TYPE_META_DESCRIPTION], self::TYPE_META_DESCRIPTION);

        # focus_keyphrase
        $this->content[self::TYPE_FOCUS_KEYPHRASE] = $this->filterFocusKeyphrase($this->createContentType($this->content[self::TYPE_WRITEUP], self::TYPE_FOCUS_KEYPHRASE));
        $this->setCache($this->content[self::TYPE_FOCUS_KEYPHRASE], self::TYPE_FOCUS_KEYPHRASE);

        return $this->content;
    }

    /**
     * Makes a call to the OpenAI GPT API based on a text input and a content type.
     *
     * @param string $text
     * @param string $type
     * @param int|null $numtokens
     * @param bool $multipart
     * @param bool $recursion
     * @return string
     */
    public function createContentType(string $text, string $type, int $tokenLimit = null, $multipart = false, $recursion = false): string
    {
        # If content is not being recursively generated, feed from cache
        if (!$recursion) {
            $cache = $this->getCache($type);
            if (false !== $cache) return $cache;
        }

        if (null === $tokenLimit) {
            $tokenLimit = self::MAX_TOKENS_PER_MESSAGE;
        }

        $title = null;

        if (isset($this->content[self::TYPE_TITLE])) {
            $title = $this->content[self::TYPE_TITLE];
        }

        $prompt = $this->prompts[$type]($title);
        [$truncated, $remainder] = $this->smartTruncateByTokens($text, $prompt);

        $response = $this->chat->generate($this->getRequest(Tokenizer::decode($truncated), $prompt));
        $content = $this->getContentFromResponse($response);

        $remainderCount = count($remainder);
        if (($remainderCount > 0) && $multipart) {
            $remainderContent = $this->createContentType(Tokenizer::decode($remainder), $type, $tokenLimit, true, true);
            $content =  $content . "\n\n" . $remainderContent;
        }

        return $content;
    }

    /**
     * Take a content string and Truncate it by the number of tokens.
     *
     * @param string $content
     * @param string $prompt
     * @param integer|null $tokenLimit
     * @return string
     */
    public function smartTruncateByTokens(string $content, string $prompt, int $tokenLimit = null): array
    {
        if (null === $tokenLimit) {
            $tokenLimit = self::MAX_TOKENS_PER_MESSAGE;
        }

        $tokens = Tokenizer::encode($content);
        $promptTokens = Tokenizer::encode($prompt);
        $numPromptTokens = count($promptTokens);
        $maxSlice = $tokenLimit - $numPromptTokens;

        $truncated = array_slice($tokens, 0, $maxSlice);
        $remainder = array_slice($tokens, $maxSlice);

        return [$truncated, $remainder];
    }

    /**
     * Take a content string and Truncate it by the number of tokens.
     *
     * @param string $content
     * @param integer|null $numTokens
     * @return string
     */
    public function truncateByTokens(string $content, int $numTokens = null): string
    {
        if (null === $numTokens) {
            $numTokens = self::MAX_TOKENS_PER_MESSAGE;
        }

        $tokens = Tokenizer::encode($content);
        $truncated = array_slice($tokens, 0, $numTokens);

        return Tokenizer::decode($truncated);
    }

    /**
     * Applies certain filters to the writeup.
     *
     * @param string $text
     * @return string
     */
    protected function filterWriteup(string $text): string 
    {
        $cleaned = $this->filterUnicode($text);
        $cleaned = preg_replace('/(\.)([[:alpha:]]{2,})/', '$1 $2', $cleaned);
        return $cleaned;
    }

    /**
     * Modify content to be formatted as HTML
     *
     * @param string $text
     * @param string $type
     * @return string
     */
    protected function fiterHtml(string $text, string $type): string
    {
        # If content is already cached just return the cache
        $cache = $this->getCache($type);
        if (false !== $cache) return $cache;

        $this->removeTmp($type);

        $text = $this->filterUnicode($text);

        $content = '<p>' . str_replace("\n\n", "</p>\n\n<p>", $text) . '</p>';

        $this->setTmp($content, $type);

        return $content;
    }

    /**
     * Checks a datetime string to determine if the date is valid
     *
     * @param string $date
     * @param string $format
     * @return boolean
     */
    protected function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Removes unwanted text from the meta description.
     *
     * @param string $content
     * @return string
     */
    protected function filterMetaDescription(string $content): string
    {
        $content = trim($this->filterDoubleQuotes($content));
        $content = $this->filterUnicode($content);
        # DB column is varchar 255 so make sure it doesn't exceed column length
        $content = substr($content, 0, 255);

        return $content;
    }

    /**
     * Removes unwanted text from the focus keyphrase.
     *
     * @param string $content
     * @return string
     */
    protected function filterFocusKeyphrase(string $content): string
    {
        # Remove commas
        $content = str_replace(',', '', $content);

        # Trim and remove quotes
        $content = trim($this->filterDoubleQuotes($content));
        $content = $this->filterUnicode($content);
        # DB column is varchar 255 so make sure it doesn't exceed column length
        $content = substr($content, 0, 255);

        return $content;
    }

    /**
     * Text filter for dates to return null if the datetime string is not valid
     *
     * @param string $content
     * @return string|null
     */
    protected function filterPublicationDate(string $content): string|null
    {
        return ($this->validateDate($content)) ? $content : null;
    }

    /**
     * Text filter for title to apply text fixes
     *
     * @param string $content
     * @return string
     */
    protected function filterTitle(string $content): string 
    {
        $content = str_replace('Titles: ', '', $content);
        $content = str_replace('Title: ', '', $content);
        $content = $this->filterDoubleQuotes($content);
        $content = trim($content);
        $content = $this->filterUnicode($content);
        return substr($content, 0, 255);
    }

    /**
     * Text filter for tweet to apply text fixes
     *
     * @param string $content
     * @return string
     */
    protected function filterTweet(string $content): string 
    {
        # Remove "Tweet: " preamble from string
        $content = str_replace('Tweet: ', '', $content);

        # Remove non-ascii characters
        $content = preg_replace('/[^\x20-\x7E]/','', $content);

        $content = $this->filterUnicode($content);

        # Remove Double Quotes, Trim and truncate to 255 characters
        return substr(trim($this->filterDoubleQuotes($content)), 0, 255);
    }

    /**
     * Filters the text in a list of tags to correct the text
     *
     * @param array $tags
     * @return array
     */
    protected function filterTags(array $tags): array
    {   
        $newTags = [];

        foreach($tags as $tag) {
            $newTags[] = $this->filterTag($tag);
        }

        return $newTags;
    }

    /**
     * Fixes any text problems with a tag
     *
     * @param string $tag
     * @return string
     */
    protected function filterTag(string $tag): string
    {
        if (strlen($tag) > 100) {
            $tag = substr($tag, 0, 50);
        }

        $tag = preg_replace('/keyword \d*\:/i', '', $tag);

        $tag = $this->filterUnicode($tag);

        return trim($this->filterDoubleQuotes($tag));
    }

    /**
     * Filters the text in a list of authors to correct the text
     *
     * @param array $authors
     * @return array
     */
    protected function filterAuthors(array $authors): array
    {   
        $newAuthors = [];

        foreach($authors as $author) {
            $newAuthors[] = $this->filterAuthor($author);
        }

        return $newAuthors;
    }

    /**
     * Fixes any text problems with an author
     *
     * @param string $author
     * @return string
     */
    protected function filterAuthor(string $author): string
    {
        $author = $this->filterUnicode($author);

        return trim($this->filterDoubleQuotes($author));
    }

    /**
     * Removes double quotes from string
     *
     * @param string $content
     * @return string
     */
    protected function filterDoubleQuotes(string $content): string {

        return str_replace('"', "", $content);
    }

    /**
     * Converts Unicode strings into utf-8
     *
     * @param string $text
     * @return string
     */
    protected function filterUnicode(string $text): string {
        return html_entity_decode(preg_replace("/U\+([0-9A-F]{4})/s", "&#x\\1;", $text), ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * Produces an OpenAiRequest object based on a prompt and text that the prompt refers to.
     *
     * @param string $text
     * @param string $prompt
     * @param int $tokenLimit
     * @return OpenAiRequest
     */
    protected function getRequest(string $text, string $prompt, int $tokenLimit = null): OpenAiRequest
    {
        #sanitize the text
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $persona = $this->chat->getPersona();

        if (null === $tokenLimit) {
            $tokenLimit = self::MAX_TOKENS_PER_MESSAGE;
        }

        return new OpenAiRequest([
            'max_tokens' => $tokenLimit,
            'messages' => [
                [
                    'role'      => 'system',
                    'content'   => sprintf('You are %s.', $persona)
                ],
                [
                    'role'      => 'user',
                    'content'   => "$prompt
                    
                    $text",
                ],
            ],
        ]);
    }

    /**
     * Takes a Response object with Data from an OpenAI chat request, and converts it into a string.
     *
     * @param Response $response
     * @return string
     */
    protected function getContentFromResponse(Response $response): string
    {
        $data = $response->json();
        if (isset($data['choices']) && isset($data['choices'][0]) && isset($data['choices'][0]['message'])) {
            $finishReason = $data['choices'][0]['finish_reason'];
            if ('stop' !== $finishReason && 'length' !== $finishReason) {
                throw new \Exception("Unusual termination from API: $finishReason");
            } else {
                return $data['choices'][0]['message']['content'];
            }
        } else {
            throw new \Exception("Unusable response from API\n\n" . print_r($data) );
        }
    }

    /**
     * Get openAI chatcompletion implementation.
     *
     * @return  ChatCompletion
     */ 
    public function getChat(): ChatCompletion
    {
        return $this->chat;
    }

    /**
     * Set openAI chatcompletion implementation.
     *
     * @param  ChatCompletion  $chat  OpenAI chatcompletion implementation.
     *
     * @return  self
     */ 
    public function setChat(ChatCompletion $chat): Manager
    {
        $this->chat = $chat;

        return $this;
    }


    /**
     * Get list of prompts by content type.
     *
     * @return  array
     */ 
    public function getPrompts()
    {
        if ($this->prompts === null) {
            $this->prompts = [
                Manager::TYPE_TITLE             => function() {
                                                    return Manager::PROMPT_TITLE;
                                                },
                Manager::TYPE_BLURB             => function($title = null) {
                                                    if (null === $title) {
                                                        return Manager::PROMPT_BLURB;
                                                    } else {
                                                        return sprintf("Given this title \"%s\", %s",
                                                            $title, Manager::PROMPT_BLURB
                                                        );
                                                    }
                                                },
                Manager::TYPE_PUBLICATION_DATE  => function() {
                                                    return Manager::PROMPT_PUBLICATION_DATE;
                                                },
                Manager::TYPE_TWEET             => function() {
                                                    return Manager::PROMPT_TWEET;
                                                },
                Manager::TYPE_AUTHORS           => function() {
                                                    return Manager::PROMPT_AUTHORS;
                                                },
                Manager::TYPE_TAGS              => function() {
                                                    return Manager::PROMPT_TAGS;
                                                },
                Manager::TYPE_WRITEUP           => function() {
                                                    return Manager::PROMPT_WRITEUP;
                                                },
                Manager::TYPE_SUMMARY           => function() {
                                                    return Manager::PROMPT_SUMMARY;
                                                },
                Manager::TYPE_FOCUS_KEYPHRASE   => function($title = null) {
                                                    if (null === $title) {
                                                        return Manager::PROMPT_FOCUS_KEYPHRASE;
                                                    } else {
                                                        return sprintf("Given this title \"%s\", %s",
                                                            $title, Manager::PROMPT_FOCUS_KEYPHRASE
                                                        );
                                                    }
                                                },
                Manager::TYPE_META_DESCRIPTION  => function($title = null) {
                                                    if (null === $title) {
                                                        return Manager::PROMPT_META_DESCRIPTION;
                                                    } else {
                                                        return sprintf("Given this title \"%s\", %s",
                                                            $title, Manager::PROMPT_META_DESCRIPTION
                                                        );
                                                    }
                                                },
            ];
        }

        return $this->prompts;
    }
}
