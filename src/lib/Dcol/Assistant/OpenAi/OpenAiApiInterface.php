<?php

namespace Dcol\Assistant\OpenAi;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;

use Dcol\Assistant\Request\AssistantRequestInterface;

interface OpenAiApiInterface
{
    public function getResponseTimeout(): int;

    public function setResponseTimeout(int $responseTimeout): OpenAiApiInterface;

    public function getAuthKey(): string;

    public function setAuthKey(string $authKey): OpenAiApiInterface;

    public function getModel(): string;

    public function setModel(string $model): OpenAiApiInterface;

    public function getRetryAttempts(): int;

    public function setRetryAttempts(int $retryAttempts): OpenAiApiInterface;

    public function getEndpoint(): string;

    public function setEndpoint(string $endpoint): OpenAiApiInterface;

    public function generate(AssistantRequestInterface $request): Response;
}
