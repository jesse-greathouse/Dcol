<?php

namespace Dcol\WordPress;

use Dcol\WordPress\Auth\WordPressAuthInterface;

class Posts extends Api implements ApiInterface
{
    const DEFAULT_RESPONSE_TIMEOUT = 30;

    const DEFAULT_RETRY_ATTEMPTS = 1;

    const ENDPOINT = '/wp-json/wp/v2/posts';

    /**
     * Constructor
     *
     * @param string|null $authKey
     * @param string|null $model
     * @param int|null $retryAttempts
     */
    public function __construct(WordPressAuthInterface $auth, string $domain = null, int $retryAttempts = null, $responseTimeout = null, string $protocol = null)
    {
        $this->setEndpoint(Posts::ENDPOINT);
        
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
}
