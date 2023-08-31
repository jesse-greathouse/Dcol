<?php

namespace Dcol\Assistant\OpenAi;

class ChatCompletion extends OpenAiApi implements OpenAiApiInterface
{
    const DEFAULT_RESPONSE_TIMEOUT = 60;

    const DEFAULT_RETRY_ATTEMPTS = 5;

    const DEFAULT_MODEL = 'gpt-3.5-turbo';

    const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Constructor
     *
     * @param string|null $authKey
     * @param string|null $model
     * @param int|null $retryAttempts
     */
    public function __construct(string $authKey = null, string $model = null, int $retryAttempts = null, $responseTimeout = null)
    {
        $this->setEndpoint(ChatCompletion::ENDPOINT);
        
        if (null !== $authKey) {
            $this->setAuthKey($authKey);
        }

        if (null === $model) {
            $this->setModel(ChatCompletion::DEFAULT_MODEL);
        } else {
            $this->setModel($model);
        }

        if (null === $retryAttempts) {
            $this->setRetryAttempts(ChatCompletion::DEFAULT_RETRY_ATTEMPTS);
        } else {
            $this->setRetryAttempts($retryAttempts);
        }

        if (null === $responseTimeout) {
            $this->setResponseTimeout(ChatCompletion::DEFAULT_RESPONSE_TIMEOUT);
        } else {
            $this->setResponseTimeout($responseTimeout);
        }

    }
}
