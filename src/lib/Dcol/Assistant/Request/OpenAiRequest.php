<?php

namespace Dcol\Assistant\Request;

class OpenAiRequest implements AssistantRequestInterface
{
    /**
     * Request Body
     *
     * @var array
     */
    protected $body = [];

    /**
     * Request Headers
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Constructor allows shortcutting the header and body input on instantiation
     *
     * @param array|null $header
     * @param array|null $body
     */
    public function __construct(array $body = null, array $headers = null) {
        if (null !== $body) {
            $this->setBody($body);
        }

        if (null !== $headers) {
            $this->setHeaders($headers);
        }

        # Force the Content-Type header to be "<application/json>" If it isn't already set
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = "application/json";
        }
    }

    /**
     * Sets the GPT authorization secret for the request
     *
     * @param string $authKey
     * @return OpenAiRequest
     */
    public function authKey(string $authKey): OpenAiRequest
    {
        # Force the Authorization header 
        $this->headers['Authorization'] = "Bearer $authKey";
        return $this;
    }

    /**
     * Returns true/false if the request has an auth header
     *
     * @return boolean
     */
    public function hasAuthKey(): bool
    {
        return (isset($this->headers['Authorization']));
    }

    /**
     * Sets the GPT model for the request
     *
     * @param string $model
     * @return OpenAiRequest
     */
    public function model(string $model): OpenAiRequest 
    {
        $this->body['model'] = $model;
        return $this;
    }

    /**
     * Returns true/false if the request has specified a model
     *
     * @return boolean
     */
    public function hasModel(): bool
    {
        return (isset($this->body['model']));
    }

    /**
     * Returns true/false if the request has specified a file
     *
     * @return boolean
     */
    public function hasFile(): bool
    {
        return (isset($this->body['file']));
    }

    /**
     * Sets the file for the request
     *
     * @param $file
     * @return OpenAiRequest
     */
    public function file($file): OpenAiRequest 
    {
        $this->body['file'] = $file;
        return $this;
    }

    /**
     * Returns true/false if the request has specified a fileId
     *
     * @return boolean
     */
    public function hasFileId(): bool
    {
        return (isset($this->body['training_file']));
    }

    /**
     * Sets the fileId for the request
     *
     * @param string $fileId
     * @return OpenAiRequest
     */
    public function fileId(string $fileId): OpenAiRequest 
    {
        $this->body['training_file'] = $fileId;
        return $this;
    }

    /**
     * Returns true/false if the request has specified a suffix
     *
     * @return boolean
     */
    public function hasSuffix(): bool
    {
        return (isset($this->body['suffix']));
    }

    /**
     * Sets the suffix for the request
     *
     * @param string $suffix
     * @return OpenAiRequest
     */
    public function suffix(string $suffix): OpenAiRequest 
    {
        $this->body['suffix'] = $suffix;
        return $this;
    }

    /**
     * Returns true/false if the request has specified a purpose
     *
     * @return boolean
     */
    public function hasPurpose(): bool
    {
        return (isset($this->body['purpose']));
    }

    /**
     * Sets the purpose for the request
     *
     * @param string $purpose
     * @return OpenAiRequest
     */
    public function purpose(string $purpose): OpenAiRequest 
    {
        $this->body['purpose'] = $purpose;
        return $this;
    }

    public function addMessage(string $content, string $role = 'user'): OpenAiRequest 
    {
        # Make sure the messages list exists
        if (!isset($this->body['messages'])) {
            $this->body['messages'] = [];
        }

        $newMessage = ['role' => $role, 'cotnent' => $content];

        array_push($this->body['messages'], ...$newMessage);

        return $this;
    }

    /**
     * Returns the Body which should be a json array representation of Body.
     *
     * @return array
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Sets the body which is an array.
     *
     * @param array $body
     * @return void
     */
    public function setBody(array $body): void
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
     * @return OpenAiRequest
     */
    public function addHeader(string $key, string $val): AssistantRequestInterface
    {
        if (isset($this->header[$key])) {
            throw new \Exception(
                "Tried to add header value to request but header: \"$key\" already exists"
            );
        } else {
            $this->header[$key] = $val;
        }
    }

    /**
     * Adds a header body to the request
     *
     * @param string $key
     * @param string|array $val
     * @return OpenAiRequest
     */
    public function addBody(string $key, string|array $val): AssistantRequestInterface
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
