<?php

namespace Dcol\WordPress;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ConnectionException;

use Dcol\WordPress\Request\WordPressRequestInterface,
    Dcol\WordPress\Auth\WordPressAuthInterface;

/**
 * Abstract implementation of WordPress API Client
 */
abstract class Api {

    const DEFAULT_PROTOCOL = 'https';
    
    /**
     * url protocol string
     *
     * @var string
     */
    protected $protocol;

    /**
     * url domain string
     * 
     * @var
     */
    protected $domain;

    /**
     * The allowable length of time, in seconds, to wait for the response
     *
     * @var int
     */
    protected $responseTimeout;

    /**
     * Number of attempts to retry in the event of a request failure
     *
     * @var int
     */
    protected $retryAttempts;

    /**
     * The API Endpoint
     *
     * @var string
     */
    protected $endpoint;

    /**
     * WordPress authorization implementation
     *
     * @var WordPressAuthInterface
     */
    protected $auth;

    /**
     * File attachment path for upload.
     *
     * @var string
     */
    protected $attachment;

    /**
     * Submit via POST
     *
     * @param WordPressRequestInterface $request
     * @return Response
     */
    public function post(WordPressRequestInterface $request, ?int $id = null): Response
    {
        $e = null;
        $additionalMessaging = '';
        $attempts = 0;

        $request = $this->getAuth()->addAuth($request);

        do {
            try
            {
                $attachment = $this->getAttachment();
                $url = $this->getUrl($id);
                $headers = $request->getHeaders();
                $body = $request->getBody();
               
                if (null !== $attachment) {
                    $fileName = basename($attachment);
                    $stream = fopen($attachment, 'r');
                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);

                    // When it's an image, unset Content-Type to dynamically calculate the boundary
                    // But when it's a PDF this breaks it XD
                    if ($ext === 'png' || $ext === 'jpg') {
                        unset($headers['Content-Type']);
                    }

                    $response = Http::withHeaders($headers)
                        ->timeout($this->getResponseTimeout())
                        ->attach('file', $stream, $fileName)
                        ->post($url);
                } else {
                    $response = Http::withHeaders($headers)
                        ->timeout($this->getResponseTimeout())
                        ->post($url, $body);
                }

                $response->throw();
            } catch (\Exception $e) {
                $additionalMessaging = 'url: ' . $url  . "\n\n";
                if (null !== $attachment) {
                    $additionalMessaging .= 'attachment: ' .  $attachment . "\n\n";
                }
                $additionalMessaging .= 'request headers: ' . print_r($headers, true) . "\n\n";
                $additionalMessaging .= 'request body: ' . print_r($body, true) . "\n\n";
                $attempts++;
                sleep(1);
                continue;
            }
            break;
        } while($attempts < $this->getRetryAttempts());

        # Nullify any attachment
        $this->setAttachment(null);

        if (null !== $e) {
            throw new \Exception($e->getMessage() . "\n\n" . $additionalMessaging);
        }

        return $response;
    }

    /**
     * GET
     *
     * @param WordPressRequestInterface $request
     * @return Response
     */
    public function get(WordPressRequestInterface $request, ?int $id = null): Response
    {
        $e = null;
        $additionalMessaging = '';
        $attempts = 0;

        $request = $this->getAuth()->addAuth($request);

        do {
            try
            {
                $url = $this->getUrl($id);
                $headers = $request->getHeaders();
                $body = $request->getBody();

                $response = Http::withHeaders($headers)
                    ->timeout($this->getResponseTimeout())
                    ->get($url, $body);
                $response->throw();
            } catch (\Exception $e) {
                $additionalMessaging = 'url: ' . $url  . "\n\n";
                #$additionalMessaging .= 'response body: ' .  print_r($response->json(), true) . "\n\n";
                $additionalMessaging .= 'request headers: ' . print_r($headers, true) . "\n\n";
                $additionalMessaging .= 'request body: ' . print_r($body, $true) . "\n\n";
                $attempts++;
                sleep(1);
                continue;
            }
            break;
        } while($attempts < $this->getRetryAttempts());

        if (null !== $e) {
            throw new \Exception($e->getMessage() . "\n\n" . $additionalMessaging);
        }

        return $response;
    }

    /**
     * DELETE
     *
     * @param WordPressRequestInterface $request
     * @return Response
     */
    public function delete(WordPressRequestInterface $request, int $id): Response
    {
        $e = null;
        $additionalMessaging = '';
        $attempts = 0;

        $request = $this->getAuth()->addAuth($request);

        do {
            try
            {
                $url = $this->getUrl($id);
                $headers = $request->getHeaders();
                $body = $request->getBody();
    
                $response = Http::withHeaders($headers)
                    ->timeout($this->getResponseTimeout())
                    ->delete($url, $body);
                $response->throw();
            } catch (\Exception $e) {
                $additionalMessaging = 'url: ' . $url  . "\n\n";
                #$additionalMessaging .= 'response body: ' .  print_r($response->json(), true) . "\n\n";
                $additionalMessaging .= 'request headers: ' . print_r($headers, true) . "\n\n";
                $additionalMessaging .= 'request body: ' . print_r($body, true) . "\n\n";
                $attempts++;
                sleep(1);
                continue;
            }
            break;
        } while($attempts < $this->getRetryAttempts());

        if (null !== $e) {
            throw new \Exception($e->getMessage() . "\n\n" . $additionalMessaging);
        }

        return $response;
    }

    /**
     * Dynamically forms the URL
     *
     * @param integer|null $id
     * @return void
     */
    public function getUrl(int $id = null) {
        $url = sprintf('%s://%s%s',
            $this->getProtocol(),
            $this->getDomain(),
            $this->getEndpoint(),
        );

        if (null !== $id) {
            $url .= "/$id";
        }

        return $url;
    }

    /**
     * Get number of attempts to retry in the event of a request failure
     *
     * @return  int
     */ 
    public function getRetryAttempts(): int
    {
        return $this->retryAttempts;
    }

    /**
     * Set number of attempts to retry in the event of a request failure
     *
     * @param  int  $retryAttempts  Number of attempts to retry in the event of a request failure
     *
     * @return  self
     */ 
    public function setRetryAttempts(int $retryAttempts): ApiInterface
    {
        $this->retryAttempts = $retryAttempts;

        return $this;
    }

    /**
     * Get the API Endpoint
     *
     * @return  string
     */ 
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Set the API Endpoint
     *
     * @param  string  $endpoint  The API Endpoint
     *
     * @return  self
     */ 
    public function setEndpoint(string $endpoint): ApiInterface
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Get the allowable length of time, in seconds, to wait for the response
     *
     * @return  int
     */ 
    public function getResponseTimeout(): int
    {
        if (null === $this->responseTimeout) {
            $this->responseTimeout = -1;
        }

        return $this->responseTimeout;
    }

    /**
     * Set the allowable length of time, in seconds, to wait for the response
     *
     * @param  int  $responseTimeout  The allowable length of time, in seconds, to wait for the response
     *
     * @return  self
     */ 
    public function setResponseTimeout(int $responseTimeout): ApiInterface
    {
        $this->responseTimeout = $responseTimeout;

        return $this;
    }

    /**
     * Get wordPress authorization implementation
     *
     * @return  WordPressAuthInterface
     */ 
    public function getAuth(): WordPressAuthInterface
    {
        return $this->auth;
    }

    /**
     * Set wordPress authorization implementation
     *
     * @param  WordPressAuthInterface  $auth  WordPress authorization implementation
     *
     * @return  self
     */ 
    public function setAuth(WordPressAuthInterface $auth): ApiInterface
    {
        $this->auth = $auth;

        return $this;
    }

    /**
     * Get url protocol string
     *
     * @return  string
     */ 
    public function getProtocol(): string
    {
        if (null === $this->protocol ) {
            $this->protocol = static::DEFAULT_PROTOCOL;
        }
        return $this->protocol;
    }

    /**
     * Set url protocol string
     *
     * @param  string  $protocol  url protocol string
     *
     * @return  self
     */ 
    public function setProtocol(string $protocol): ApiInterface
    {
        $this->protocol = $protocol;

        return $this;
    }

    /**
     * Get url domain string
     *
     * @return  string
     */ 
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Set url domain string
     *
     * @param  string  $domain  url domain string
     *
     * @return  self
     */ 
    public function setDomain(string $domain): ApiInterface
    {
        $this->domain = $domain;

        return $this;
    }

        /**
     * Get file attachment path for upload.
     *
     * @return  string
     */ 
    public function getAttachment(): string|null
    {
        return $this->attachment;
    }

    /**
     * Set file attachment path for upload.
     *
     * @param  string|null  $attachment  File attachment path for upload.
     *
     * @return  self
     */ 
    public function setAttachment(string|null $attachment): ApiInterface
    {
        $this->attachment = $attachment;

        return $this;
    }
}
