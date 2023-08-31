<?php

namespace Dcol\WordPress\Request;

interface WordPressRequestInterface
{
    /**
     * Must have a getBody() method that returns an array.
     *
     * @return mixed
     */
    public function getBody();

    /**
     * Must have a setBody() method that accepts an array.
     *
     * @param mixed $body
     * @return void
     */
    public function setBody($body): void;

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
     * Must have an addHeader() method that returns an instance of WordPressRequestInterface.
     *
     * @param string $key
     * @param string|array $val
     * @return AssistantRequestInterface
     */
    public function addHeader(string $key, string $val): WordPressRequestInterface;

    /**
     * Must have an addBody() method that returns an instance of WordPressRequestInterface.
     *
     * @param string $key
     * @param string|array $val
     * @return AssistantRequestInterface
     */
    public function addBody(string $key, string|array $val): WordPressRequestInterface;
}
