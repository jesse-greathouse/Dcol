<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\AiModel;

use Dcol\Assistant\OpenAi\File as FileApi,
    Dcol\Assistant\OpenAi\FineTuning as FineTuningApi,
    Dcol\Blog\Post\Manager as BlogPostManager,
    Dcol\Content\Manager as ContentManager,
    Dcol\Training\Manager as TrainingManager;

class FineTunedModelSync extends Command
{
    use OutputCheck, PrependsOutput, PrependsTimestamp;

    /**
     * Authorization secret for OpenAI
     *
     * @var string
     */
    protected $authKey;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dcol:finetunedmodel:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes AI Trained Models';

    /**
     * Maps Entity Keys to Api Object Keys
     * 
     * @var array
     */
    protected $map = [
        'ai_id'             => 'id',
        'ai_created_at'     => 'created_at',
        'ai_finished_at'    => 'finished_at',
        'model'             => 'model',
        'fine_tuned_model'  => 'fine_tuned_model',
        'status'            => 'status',
        'object'            => 'object',
        'trained_tokens'    => 'trained_tokens',
        'error'             => 'error',
        'organization_id'   => 'organization_id',
        'training_file'     => 'training_file',
        'validation_file'   => 'validation_file',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rs = [];
        $hasMore = true;
        $after = null;
        $limit = 20;
    
        $recordAiModelCount = $this->getAiModelsCount();

        if ($recordAiModelCount < 1) {
            if ($this->isVerbose()) {
                $this->info("No eligible models to synchronize.");
                $this->newLine();
            }
            exit(0);
        }

        $fineTuningApi = new FineTuningApi($this->getAuthKey());

        do {
            [$data, $hasMore, $after] = $this->getPage($fineTuningApi, $limit, $after);
            $rs = array_merge($rs, $data);
        } while($hasMore);

        $liveAiModelList = $this->makeLiveAiModelIndex($rs);
        $keys = array_keys($liveAiModelList);
        $liveAiModelCount = count($keys);

        if ($this->isVerbose()) {
            $this->info("Found $liveAiModelCount Ai Models to sync.");
            $this->info("Comparison against $recordAiModelCount Ai Model records.");
            $this->newLine();
        }

        foreach($this->getAiModels() as $aiModel) {
            $id = $aiModel->ai_id;

            if (!in_array($id, $keys)) {
                if ($this->isVerbose()) {
                    $this->line("Model with id: $id not found in API response");
                    $this->line("deleting...");
                    $this->newLine();
                }
                $aiModel->delete();
                continue;
            }

            foreach($this->map as $modelKey => $apiKey) {
                if (isset($liveAiModelList[$id][$apiKey])) {
                    $aiModel->{$modelKey} = $liveAiModelList[$id][$apiKey];
                }
            }

            if (isset($liveAiModelList[$id]['result_files'])) {
                $aiModel->result_files = $this->jsonField('result_files', $liveAiModelList[$id]);
            }

            if (isset($liveAiModelList[$id]['hyperparameters'])) {
                $aiModel->hyperparameters = $this->jsonField('hyperparameters', $liveAiModelList[$id]);
            }

            $aiModel->save();
        }

        if ($this->isVerbose()) {
            $this->info("Finished Syncing AI Models");
            $this->newLine();
        }
    }

    /**
     * Attempts to convert an array into a json string. Returns null if it cannot.
     *
     * @param string $name
     * @param array $data
     * @return string|null
     */
    protected function jsonField(string $name, array $data): string|null
    {
        if (isset($data[$name])) {
            try {
                $value = json_encode($data[$name]);
            } catch(\Exception $e) {
                $value = null;
            }
        } else {
            $value = null;
        }

        return $value;
    }

    /**
     * Turns an API response into a dictionary indexed by file id
     *
     * @param array $data
     * @return array
     */
    protected function makeLiveAiModelIndex($data): array
    {
        $list = [];
        $keys = array_values($this->map);
        $keys[] = 'result_files';
        $keys[] = 'hyperparameters';
        foreach($data as $element) {
            $id = $element['id'];
            foreach($keys as $key) {
                $list[$id][$key] = $element[$key];
            }
        }

        return $list;
    }

    /**
     * Returns a page of data from the API model list endpoint.
     *
     * @param FineTuningApi $fineTuningApi
     * @param integer $limit
     * @param string|null $after
     * @return array
     */
    protected function getPage(FineTuningApi $fineTuningApi, int $limit, string $after = null): array
    {
        $res = $fineTuningApi->list($limit, $after);
        $data = $res->json();

        $rs = [];
        $hasMore = false;
        $after = null;

        if (isset($data['has_more'])) {
            $hasMore = $data['has_more'];
        } else {
            $hasMore = false;
        }

        if (isset($data['data'])) {
            $rs = $data['data'];
            if (count($data['data']) > 0) {
                $last = array_pop($data['data']);
                $after = $last['id'];
            }
        }

        return [$rs, $hasMore, $after];
    }

    /**
     * Returns a collection of published Blog Posts for the blog
     *
     * @return Collection
     */
    protected function getAiModels(): Collection
    {
        return $this->getAiModelsQb()->get();
    }

    /**
     * Returns the number of published Blog Posts for the blog.
     *
     * @return int
     */
    protected function getAiModelsCount(): int
    {
        return $this->getAiModelsQb()->count();
    }

    /**
     * Returns a query builder with the AI Models Query.
     *
     * @return Builder
     */
    protected function getAiModelsQb(): Builder
    {
        $qb = AiModel::where('active', 1);

        $qb = $qb->where(function (Builder $query) {
            $query->where('status', AiModel::STATUS_CREATED)
                ->orWhere('status', AiModel::STATUS_PENDING)
                ->orWhere('status', AiModel::STATUS_RUNNING)
                ->orWhere('status', AiModel::STATUS_VALIDATING_FILES);

        });

        return $qb;
    }

    /**
     * Returns the API AuthKey
     *
     * @return string
     */
    public function getAuthKey(): string 
    {
        if (null === $this->authKey) {
            $this->authKey = env('OPENAI_SECRET');
        }

        return $this->authKey;
    }
}
