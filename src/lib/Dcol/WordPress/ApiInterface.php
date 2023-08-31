<?php

namespace Dcol\WordPress;

use Illuminate\Http\Client\Response;

use Dcol\WordPress\Request\WordPressRequestInterface,
    Dcol\WordPress\Auth\WordPressAuthInterface;

interface ApiInterface
{
    public function getProtocol(): string;

    public function setProtocol(string $protocol): ApiInterface;

    public function getDomain(): string;

    public function setDomain(string $domain): ApiInterface;

    public function getResponseTimeout(): int;

    public function setResponseTimeout(int $responseTimeout): ApiInterface;

    public function getAuth(): WordPressAuthInterface;

    public function setAuth(WordPressAuthInterface $auth): ApiInterface;

    public function getRetryAttempts(): int;

    public function setRetryAttempts(int $retryAttempts): ApiInterface;

    public function getEndpoint(): string;

    public function setEndpoint(string $endpoint): ApiInterface;

    public function post(WordPressRequestInterface $request, ?int $id = null): Response;

    public function get(WordPressRequestInterface $request, ?int $id = null): Response;

    public function delete(WordPressRequestInterface $request, int $id): Response;
}
