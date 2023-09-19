<?php

namespace Dcol\Assistant\OpenAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

use Dcol\Assistant\Request\OpenAiRequest;

class FineTuning extends OpenAiApi implements OpenAiApiInterface
{
    const DEFAULT_RESPONSE_TIMEOUT = 20;

    const DEFAULT_MODEL = 'gpt-3.5-turbo';

    const DEFAULT_LIMIT = 20;

    const DEFAULT_RETRY_ATTEMPTS = 1;

    const ENDPOINT = 'https://api.openai.com/v1/fine_tuning/jobs';

    /**
     * OpenAI file ID String
     *
     * @var string
     */
    protected $fileId;

    /**
     * Unique identifer for a model resulting from this job.
     *
     * @var string
     */
    protected $suffix;

    /**
     * Constructor
     *
     * @param string|null $authKey
     * @param string|null $fileId
     * @param string|null $suffix
     * @param int|null $retryAttempts
     */
    public function __construct(string $authKey = null, string $fileId = null, string $suffix = null, string $model = null, int $retryAttempts = null, $responseTimeout = null)
    {
        $this->setEndpoint(FineTuning::ENDPOINT);
        
        if (null !== $authKey) {
            $this->setAuthKey($authKey);
        }

        if (null !== $fileId) {
            $this->setFileId($fileId);
        }

        if (null !== $suffix) {
            $this->setSuffix($suffix);
        }

        if (null === $model) {
            $this->setModel(FineTuning::DEFAULT_MODEL);
        } else {
            $this->setModel($model);
        }

        if (null === $retryAttempts) {
            $this->setRetryAttempts(FineTuning::DEFAULT_RETRY_ATTEMPTS);
        } else {
            $this->setRetryAttempts($retryAttempts);
        }

        if (null === $responseTimeout) {
            $this->setResponseTimeout(FineTuning::DEFAULT_RESPONSE_TIMEOUT);
        } else {
            $this->setResponseTimeout($responseTimeout);
        }
    }

    /**
     * Create a fine Tuning Job. 
     * 
     * @param AssistantRequestInterface|null $request
     * @return Response
     */
    public function create(AssistantRequestInterface $request = null): Response
    {
        $e = null;
        $additionalMessaging = '';
        $attempts = 0;

        if (null === $request) {
            $request = new OpenAiRequest();
        }

        if (!$request->hasFileId()) {
            $request = $request->fileId($this->getFileId());
        }

        if (!$request->hasSuffix()) {
            $request = $request->suffix($this->getSuffix());
        }

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
                sleep(20);
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
     * Returns a list of Fine Tuning Jobs
     *
     * @param AssistantRequestInterface|null $request
     * @return Response
     */
    public function list($limit = null, $after = null, AssistantRequestInterface $request = null): Response
    {
        $e = null;
        $attempts = 0;
        $query = [];

        if (null === $request) {
            $request = new OpenAiRequest();
        }

        if (null !== $after) {
            $query['after'] = $after;
        }

        if (null !== $limit) {
            $query['limit'] = $limit;
        } else {
            $query['limit'] = self::DEFAULT_LIMIT;
        }

        if (!$request->hasAuthKey() && null !== $this->getAuthKey() ){
            $request = $request->authKey($this->getAuthKey());
        }

        do {
            try
            {
                $response = Http::withHeaders($request->getHeaders())
                    ->timeout($this->getResponseTimeout())
                    ->get($this->getEndpoint(), $query);
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
     * Cancel a job.
     *
     * @param string $jobId
     * @param AssistantRequestInterface|null $request
     * @return Response
     */
    public function cancel(string $jobId, AssistantRequestInterface $request = null ): Response
    {
        $e = null;
        $attempts = 0;
        $endpoint = $this->getEndpoint() . '/' . $jobId . '/cancel';

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
                    ->post($endpoint);
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
     * Returns information about a specific job.
     *
     * @param string $jobId
     * @param AssistantRequestInterface|null $request
     * @return Response
     */
    public function retrieve(string $jobId, AssistantRequestInterface $request = null): Response
    {
        $e = null;
        $attempts = 0;
        $endpoint = $this->getEndpoint() . '/' . $jobId;

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
     * Returns events information about a specific job.
     *
     * @param string $jobId
     * @param integer $limit
     * @param integer $after
     * @param AssistantRequestInterface|null $request
     * @return Response
     */
    public function events(string $jobId, $limit = null, $after = null, AssistantRequestInterface $request = null): Response
    {
        $e = null;
        $attempts = 0;
        $endpoint = $this->getEndpoint() . '/' . $jobId . '/events';
        $query = [];

        if (null === $request) {
            $request = new OpenAiRequest();
        }

        if (null !== $after) {
            $query['after'] = $after;
        }

        if (null !== $limit) {
            $query['limit'] = $limit;
        } else {
            $query['limit'] = self::DEFAULT_LIMIT;
        }

        if (!$request->hasAuthKey() && null !== $this->getAuthKey() ){
            $request = $request->authKey($this->getAuthKey());
        }

        do {
            try
            {
                $response = Http::withHeaders($request->getHeaders())
                    ->timeout($this->getResponseTimeout())
                    ->get($endpoint, $query);
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
     * Get the fileId to use for the training job
     * @return  string|null
     */ 
    public function getFileId(): string|null
    {
        return $this->fileId;
    }

    /**
     * Set file Id to use for the training job
     *
     * @param  string  $fileId  file Id to use for the training job
     *
     * @return  self
     */ 
    public function setFileId(string $fileId): OpenAiApiInterface
    {
        $this->fileId = $fileId;

        return $this;
    }

    /**
     * Get suffix of the training job
     *
     * @return  string
     */ 
    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * Set suffix of the training job
     *
     * @param  string  $suffix of the training job
     *
     * @return  self
     */ 
    public function setSuffix(string $suffix): OpenAiApiInterface
    {
        $this->suffix = $suffix;

        return $this;
    }
}
