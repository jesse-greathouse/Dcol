<?php

namespace Dcol\WordPress;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\Request;

use Dcol\WordPress\Request\WordPressRequestInterface,
    Dcol\WordPress\Auth\WordPressAuthInterface;

class Tags extends Api implements ApiInterface
{
    const DEFAULT_RESPONSE_TIMEOUT = 30;

    const DEFAULT_RETRY_ATTEMPTS = 1;

    const ENDPOINT = '/wp-json/wp/v2/tags';

    /**
     * Constructor
     *
     * @param string|null $authKey
     * @param string|null $model
     * @param int|null $retryAttempts
     */
    public function __construct(WordPressAuthInterface $auth, string $domain = null, int $retryAttempts = null, $responseTimeout = null, string $protocol = null)
    {
        $this->setEndpoint(Tags::ENDPOINT);
        
        if (null !== $auth) {
            $this->setAuth($auth);
        }

        if (null !== $domain) {
            $this->setDomain($domain);
        }

        if (null === $retryAttempts) {
            $this->setRetryAttempts(Posts::DEFAULT_RETRY_ATTEMPTS);
        } else {
            $this->setRetryAttempts($retryAttempts);
        }

        if (null === $responseTimeout) {
            $this->setResponseTimeout(Posts::DEFAULT_RESPONSE_TIMEOUT);
        } else {
            $this->setResponseTimeout($responseTimeout);
        }

        if (null !== $protocol) {
            $this->setProtocol($protocol);
        }
    }

    public function getByValue(WordPressRequestInterface $request, string $value): Response
    {
        $e = null;
        $additionalMessaging = '';
        $attempts = 0;

        $request = $this->getAuth()->addAuth($request);

        do {
            try
            {
                $url = $this->getUrl() . '?slug=' . $value;
                $headers = $request->getHeaders();
                $body = $request->getBody();

                $response = Http::withHeaders($headers)
                    ->timeout($this->getResponseTimeout())
                    ->get($url, $body);
                $response->throw();
            } catch (\Exception $e) {
                $additionalMessaging = 'url: ' . $url  . "\n\n";
                $additionalMessaging .= 'request headers: ' . print_r($headers, true) . "\n\n";
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
}
