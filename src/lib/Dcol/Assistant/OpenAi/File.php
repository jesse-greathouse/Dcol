<?php

namespace Dcol\Assistant\OpenAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

use Dcol\Assistant\Request\OpenAiRequest;

class File extends OpenAiApi implements OpenAiApiInterface
{
    const DEFAULT_RESPONSE_TIMEOUT = 60;

    const DEFAULT_PURPOSE = 'fine-tune';

    const DEFAULT_RETRY_ATTEMPTS = 0;

    const ENDPOINT = 'https://api.openai.com/v1/files';

    /**
     * Full path of the file to be uploaded
     *
     * @var string
     */
    protected $file;

    /**
     * Purpose of the file to be uploaded
     *
     * @var string
     */
    protected $purpose;

    /**
     * Constructor
     *
     * @param string|null $authKey
     * @param string|null $file
     * @param string|null $purpose
     * @param int|null $retryAttempts
     */
    public function __construct(string $authKey = null, string $file = null, string $purpose = null, int $retryAttempts = null, $responseTimeout = null)
    {
        $this->setEndpoint(File::ENDPOINT);
        
        if (null !== $authKey) {
            $this->setAuthKey($authKey);
        }

        if (null !== $file) {
            $this->setFile($file);
        }

        if (null === $purpose) {
            $this->setPurpose(File::DEFAULT_PURPOSE);
        } else {
            $this->setPurpose($purpose);
        }

        if (null === $retryAttempts) {
            $this->setRetryAttempts(File::DEFAULT_RETRY_ATTEMPTS);
        } else {
            $this->setRetryAttempts($retryAttempts);
        }

        if (null === $responseTimeout) {
            $this->setResponseTimeout(File::DEFAULT_RESPONSE_TIMEOUT);
        } else {
            $this->setResponseTimeout($responseTimeout);
        }
    }

    /**
     * Upload a file that contains document(s) to be used across various endpoints/features. 
     * 
     * @param AssistantRequestInterface $request
     * @return Response
     */
    public function upload(AssistantRequestInterface $request = null): Response
    {
        $e = null;
        $additionalMessaging = '';
        $attempts = 0;
        $attachment = $this->getFile();

        if (null === $request) {
            $request = new OpenAiRequest();
        }

        $fileName = basename($attachment);
        $stream = fopen($attachment, 'r');

        if (!$request->hasPurpose()) {
            $request = $request->purpose($this->getPurpose());
        }

        if (!$request->hasAuthKey() && null !== $this->getAuthKey() ){
            $request = $request->authKey($this->getAuthKey());
        }

        $headers = $request->getHeaders();
        unset($headers['Content-Type']);

        do {
            try
            {
                $response = Http::withHeaders($headers)
                    ->timeout($this->getResponseTimeout())
                    ->attach('file', $stream, $fileName)
                    ->post($this->getEndpoint(), $request->getBody());
                $response->throw();
            } catch (\Exception $e) {
                $additionalMessaging = 'endpoint: ' . $this->getEndpoint() . "\n";
                $additionalMessaging .= 'headers: ' . print_r($request->getHeaders()). "\n";
                $additionalMessaging .= 'body: ' . print_r($request->getBody());
                $attempts++;
                sleep(10);
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
     * Returns a list of files that belong to the user's organization
     *
     * @param AssistantRequestInterface|null $request
     * @return Response
     */
    public function list(AssistantRequestInterface $request = null): Response
    {
        $e = null;
        $attempts = 0;

        if (null === $request) {
            $request = new OpenAiRequest();
        }

        if (!$request->hasAuthKey() && null !== $this->getAuthKey() ){
            $request = $request->authKey($this->getAuthKey());
        }

        do {
            try
            {
                $response = Http::withHeaders($request->getHeaders())
                    ->timeout($this->getResponseTimeout())
                    ->get($this->getEndpoint());
                $response->throw();
            } catch (\Exception $e) {
                $attempts++;
                sleep(10);
                continue;
            }
            break;
        } while($attempts < $this->getRetryAttempts());

        if (null !== $e) {
            throw new \Exception($e->getMessage());
        }

        return $response;
    }

    /**
     * Delete a file.
     *
     * @param string $fileId
     * @param AssistantRequestInterface|null $request
     * @return Response
     */
    public function delete(string $fileId, AssistantRequestInterface $request = null ): Response
    {
        $e = null;
        $attempts = 0;
        $endpoint = $this->getEndpoint() . '/' . $fileId;

        if (null === $request) {
            $request = new OpenAiRequest();
        }

        if (!$request->hasAuthKey() && null !== $this->getAuthKey() ){
            $request = $request->authKey($this->getAuthKey());
        }

        do {
            try
            {
                $response = Http::withHeaders($request->getHeaders())
                    ->timeout($this->getResponseTimeout())
                    ->delete($endpoint);
                $response->throw();
            } catch (\Exception $e) {
                $attempts++;
                sleep(10);
                continue;
            }
            break;
        } while($attempts < $this->getRetryAttempts());

        if (null !== $e) {
            throw new \Exception($e->getMessage());
        }

        return $response;
    }

    /**
     * Returns information about a specific file.
     *
     * @param string $fileId
     * @param AssistantRequestInterface|null $request
     * @return Response
     */
    public function retrieve(string $fileId, AssistantRequestInterface $request = null): Response
    {
        $e = null;
        $attempts = 0;
        $endpoint = $this->getEndpoint() . '/' . $fileId;

        if (null === $request) {
            $request = new OpenAiRequest();
        }

        if (!$request->hasAuthKey() && null !== $this->getAuthKey() ){
            $request = $request->authKey($this->getAuthKey());
        }

        do {
            try
            {
                $response = Http::withHeaders($request->getHeaders())
                    ->timeout($this->getResponseTimeout())
                    ->get($endpoint);
                $response->throw();
            } catch (\Exception $e) {
                $attempts++;
                sleep(10);
                continue;
            }
            break;
        } while($attempts < $this->getRetryAttempts());

        if (null !== $e) {
            throw new \Exception($e->getMessage());
        }

        return $response;
    }

    /**
     * Returns information about a specific file.
     *
     * @param string $fileId
     * @param AssistantRequestInterface|null $request
     * @return Response
     */
    public function retrieveContent(string $fileId, AssistantRequestInterface $request = null): Response
    {
        $e = null;
        $attempts = 0;
        $endpoint = $this->getEndpoint() . '/' . $fileId . '/content';

        if (null === $request) {
            $request = new OpenAiRequest();
        }

        if (!$request->hasAuthKey() && null !== $this->getAuthKey() ){
            $request = $request->authKey($this->getAuthKey());
        }

        do {
            try
            {
                $response = Http::withHeaders($request->getHeaders())
                    ->timeout($this->getResponseTimeout())
                    ->get($endpoint);
                $response->throw();
            } catch (\Exception $e) {
                $attempts++;
                sleep(10);
                continue;
            }
            break;
        } while($attempts < $this->getRetryAttempts());

        if (null !== $e) {
            throw new \Exception($e->getMessage());
        }

        return $response;
    }

    /**
     * Get full path of the file to be uploaded
     *
     * @return  string
     */ 
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Set full path of the file to be uploaded
     *
     * @param  string  $file  Full path of the file to be uploaded
     *
     * @return  self
     */ 
    public function setFile(string $file): OpenAiApiInterface
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Get purpose of the file to be uploaded
     *
     * @return  string
     */ 
    public function getPurpose(): string
    {
        return $this->purpose;
    }

    /**
     * Set purpose of the file to be uploaded
     *
     * @param  string  $purpose  Purpose of the file to be uploaded
     *
     * @return  self
     */ 
    public function setPurpose(string $purpose): OpenAiApiInterface
    {
        $this->purpose = $purpose;

        return $this;
    }
}
