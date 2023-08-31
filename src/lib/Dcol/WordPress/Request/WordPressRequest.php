<?php

namespace Dcol\WordPress\Request;

class WordPressRequest implements WordPressRequestInterface
{

    /**
     * Request Body
     *
     * @var array
     */
    protected $body;

    /**
     * Request Headers
     *
     * @var array
     */
    protected $headers;

    /**
     * Constructor allows shortcutting the header and body input on instantiation
     *
     * @param array|null $header
     * @param array|string|null $body
     */
    public function __construct(array|string $body = null, array $headers = null) {
        if (null !== $body) {
            $this->setBody($body);
        }

        if (null !== $headers) {
            $this->setHeaders($headers);
        }
    }

    /**
     * Returns true/false if the request has an auth header
     *
     * @return boolean
     */
    public function hasAuthorization(): bool
    {
        return (isset($this->headers['Authorization']));
    }

    /**
     * Returns the Body which should be a json array representation of Body.
     *
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Sets the body which is an array.
     *
     * @param mixed $body
     * @return void
     */
    public function setBody($body): void
    {
        $this->body = $body;
    }

    /**
     * Returns the headers which should be an array.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Sets the headers which is an array.
     *
     * @param array $headers
     * @return void
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * Adds a header value to the request
     *
     * @param string $key
     * @param string $val
     * @return BasicAuthRequest
     */
    public function addHeader(string $key, string $val): WordPressRequestInterface
    {
        if (isset($this->headers[$key])) {
            throw new \Exception(
                "Tried to add header value to request but header: \"$key\" already exists"
            );
        } else {
            $this->headers[$key] = $val;
        }

        return $this;
    }

    /**
     * Adds a header body to the request
     *
     * @param string $key
     * @param string|array $val
     * @return BasicAuthRequest
     */
    public function addBody(string $key, string|array $val): WordPressRequestInterface
    {
        if (isset($this->body[$key])) {
            if (is_array($this->body[$key])) {
                array_push($this->body[$key], ...$val);
            } else {
                throw new \Exception(
                    "Tried to add scalar body value to request but \"$key\" already exists"
                );
            }
        } else {
            $this->body[$key] = $val;
        }

        return $this;
    }
}
