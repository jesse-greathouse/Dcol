<?php

namespace Dcol\WordPress;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

use Dcol\WordPress\Auth\WordPressAuthInterface,
    Dcol\WordPress\Request\WordPressRequestInterface;

class Media extends Api implements ApiInterface
{
    const DEFAULT_RESPONSE_TIMEOUT = 120;

    const DEFAULT_RETRY_ATTEMPTS = 1;

    const ENDPOINT = '/wp-json/wp/v2/media';

    /**
     * Constructor
     *
     * @param string|null $authKey
     * @param string|null $model
     * @param int|null $retryAttempts
     */
    public function __construct(WordPressAuthInterface $auth, string $domain = null, int $retryAttempts = null, $responseTimeout = null, string $protocol = null)
    {
        $this->setEndpoint(Media::ENDPOINT);
        
        if (null !== $auth) {
            $this->setAuth($auth);
        }

        if (null !== $domain) {
            $this->setDomain($domain);
        }

        if (null === $retryAttempts) {
            $this->setRetryAttempts(Media::DEFAULT_RETRY_ATTEMPTS);
        } else {
            $this->setRetryAttempts($retryAttempts);
        }

        if (null === $responseTimeout) {
            $this->setResponseTimeout(Media::DEFAULT_RESPONSE_TIMEOUT);
        } else {
            $this->setResponseTimeout($responseTimeout);
        }

        if (null !== $protocol) {
            $this->setProtocol($protocol);
        }
    }
}
