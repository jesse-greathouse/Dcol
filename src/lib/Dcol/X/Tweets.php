<?php

namespace Dcol\X;

use GuzzleHttp\Subscriber\Oauth\Oauth1;

class Tweets extends Api implements ApiInterface
{
    const ENDPOINT = 'tweets';

    /**
     * Constructor
     *
     * @param Oauth1 $authKey
     */
    public function __construct(Oauth1 $auth)
    {
        $this->setEndpoint(Tweets::ENDPOINT);
        $this->setAuth($auth);
    }
}
