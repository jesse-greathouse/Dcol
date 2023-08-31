<?php

namespace Dcol\X;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\Psr7\Response;

/**
 * Abstract implementation of X API Client
 */
abstract class Api {

    /**
     * The API Endpoint
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Oauth1 implementation
     *
     * @var Oauth1
     */
    protected $auth;

    /**
     * Submit via POST
     *
     * @param array $body The Body of the request
     * @return Response
     */
    public function post(array $body): Response
    {
        $stack = HandlerStack::create();
        $stack->push($this->getAuth());

        $client = new Client([
            'base_uri'  => 'https://api.twitter.com/2/',
            'handler'   => $stack,
            'auth'      => 'oauth'
        ]);

        return $client->post($this->getEndpoint(), ['json' => $body]);
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
     * Get X authorization implementation
     *
     * @return  Oauth1
     */ 
    public function getAuth(): Oauth1
    {
        return $this->auth;
    }

    /**
     * Set Oauth1 implementation
     *
     * @param  XAuthInterface  $auth Oauth1
     *
     * @return  self
     */ 
    public function setAuth(Oauth1 $auth): ApiInterface
    {
        $this->auth = $auth;

        return $this;
    }
}
