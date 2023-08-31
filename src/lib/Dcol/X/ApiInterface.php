<?php

namespace Dcol\X;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

interface ApiInterface
{
    public function getAuth(): Oauth1;

    public function setAuth(Oauth1 $auth): ApiInterface;

    public function getEndpoint(): string;

    public function setEndpoint(string $endpoint): ApiInterface;

    public function post(array $body): Response;
}
