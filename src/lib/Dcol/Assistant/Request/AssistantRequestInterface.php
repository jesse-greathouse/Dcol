<?php

namespace Dcol\Assistant\Request;

interface AssistantRequestInterface
{
    /**
     * Must have a getBody() method that returns an array.
     *
     * @return array
     */
    public function getBody(): array;

    /**
     * Must have a setBody() method that accepts an array.
     *
     * @param array $body
     * @return void
     */
    public function setBody(array $body): void;

    /**
     * Must have a getHeaders() method that returns an array.
     *
     * @return array
     */
    public function getHeaders(): array;

    /**
     * Must have a setBody() method that accepts an array.
     *
     * @param array $headers
     * @return void
     */
    public function setHeaders(array $headers): void;

    /**
     * Must have an addHeader() method that returns an instance of AssistantRequestInterface.
     *
     * @param string $key
     * @param string|array $val
     * @return AssistantRequestInterface
     */
    public function addHeader(string $key, string $val): AssistantRequestInterface;

    /**
     * Must have an addBody() method that returns an instance of AssistantRequestInterface.
     *
     * @param string $key
     * @param string|array $val
     * @return AssistantRequestInterface
     */
    public function addBody(string $key, string|array $val): AssistantRequestInterface;
}
