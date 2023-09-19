<?php

namespace Dcol\Assistant\OpenAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;

use Dcol\Assistant\Request\AssistantRequestInterface;

/**
 * Abstract implementation of OpenAiApiInterface
 */
abstract class OpenAiApi {

    /**
     * The allowable length of time, in seconds, to wait for the response
     *
     * @var int
     */
    protected $responseTimeout;

    /**
     * OpenAI Api Secret
     *
     * @var string
     */
    protected $authKey;

    /**
     * OpenAI Model String
     *
     * @var string
     */
    protected $model;

    /**
     * A string that describes the persona of the AI Assistant.
     *
     * @var string
     */
    protected $persona;

    /**
     * Number of attempts to retry in the event of a request failure
     *
     * @var int
     */
    protected $retryAttempts;

    /**
     * The API Endpoint
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Create a chatcompletion transaction
     *
     * @param AssistantRequestInterface $request
     * @return Response
     */
    public function generate(AssistantRequestInterface $request): Response
    {
        $e = null;
        $additionalMessaging = '';
        $attempts = 0;

        if (!$request->hasModel()) {
            $request = $request->model($this->getModel());
        }

        if (!$request->hasAuthKey() && null !== $this->getAuthKey() ){
            $request = $request->authKey($this->getAuthKey());
        }

        do {
            try
            {
                $response = Http::withHeaders($request->getHeaders())
                    ->timeout($this->getResponseTimeout())
                    ->post($this->getEndpoint(), $request->getBody());
                $response->throw();
            } catch (\Exception $e) {
                $additionalMessaging = 'request: ' . json_encode($request->getBody());
                $attempts++;
                sleep(60);
                continue;
            }
            break;
        } while($attempts < $this->getRetryAttempts());

        if (null !== $e) {
            throw new \Exception($e->getMessage() . "\n\n" . $additionalMessaging);
        }

        return $response;
    }

    /**
     * Get openAI Api Secret
     *
     * @return  string
     */ 
    public function getAuthKey(): string
    {
        return $this->authKey;
    }

    /**
     * Set openAI Api Secret
     *
     * @param  string  $authKey  OpenAI Api Secret
     *
     * @return  self
     */ 
    public function setAuthKey(string $authKey): OpenAiApiInterface
    {
        $this->authKey = $authKey;

        return $this;
    }

    /**
     * Get openAI Model String
     *
     * @return  string
     */ 
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Set openAI Model String
     *
     * @param  string  $model  OpenAI Model String
     *
     * @return  self
     */ 
    public function setModel(string $model): OpenAiApiInterface
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get a string that describes the persona of the AI Assistant.
     *
     * @return  string
     */ 
    public function getPersona(): string
    {
        return $this->persona;
    }

    /**
     * Set a string that describes the persona of the AI Assistant.
     *
     * @param  string  $persona  A string that describes the persona of the AI Assistant.
     *
     * @return  self
     */ 
    public function setPersona(string $persona): OpenAiApiInterface
    {
        $this->persona = $persona;

        return $this;
    }

    /**
     * Get number of attempts to retry in the event of a request failure
     *
     * @return  int
     */ 
    public function getRetryAttempts(): int
    {
        return $this->retryAttempts;
    }

    /**
     * Set number of attempts to retry in the event of a request failure
     *
     * @param  int  $retryAttempts  Number of attempts to retry in the event of a request failure
     *
     * @return  self
     */ 
    public function setRetryAttempts(int $retryAttempts): OpenAiApiInterface
    {
        $this->retryAttempts = $retryAttempts;

        return $this;
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
    public function setEndpoint(string $endpoint): OpenAiApiInterface
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Get the allowable length of time, in seconds, to wait for the response
     *
     * @return  int
     */ 
    public function getResponseTimeout(): int
    {
        if (null === $this->responseTimeout) {
            $this->responseTimeout = -1;
        }

        return $this->responseTimeout;
    }

    /**
     * Set the allowable length of time, in seconds, to wait for the response
     *
     * @param  int  $responseTimeout  The allowable length of time, in seconds, to wait for the response
     *
     * @return  self
     */ 
    public function setResponseTimeout(int $responseTimeout): OpenAiApiInterface
    {
        $this->responseTimeout = $responseTimeout;

        return $this;
    }
}
