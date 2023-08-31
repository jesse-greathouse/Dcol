<?php

namespace Dcol\Assistant\OpenAi;

class ImageGeneration extends OpenAiApi implements OpenAiApiInterface
{
    const DEFAULT_RETRY_ATTEMPTS = 5;

    const DEFAULT_MODEL = 'gpt-3.5-turbo';

    const ENDPOINT = 'https://api.openai.com/v1/images/generations';

    /**
     * Constructor
     *
     * @param string|null $authKey
     * @param string|null $model
     * @param int|null $retryAttempts
     */
    public function __construct(string $authKey = null, string $model = null, int $retryAttempts = null)
    {
        $this->setEndpoint(ImageGeneration::ENDPOINT);
        
        if (null !== $authKey) {
            $this->setAuthKey($authKey);
        }

        if (null === $model) {
            $this->setModel(ImageGeneration::DEFAULT_MODEL);
        } else {
            $this->setRetryAttempts($model);
        }

        if (null === $retryAttempts) {
            $this->setRetryAttempts(ImageGeneration::DEFAULT_RETRY_ATTEMPTS);
        } else {
            $this->setRetryAttempts($retryAttempts);
        }
    }
}
