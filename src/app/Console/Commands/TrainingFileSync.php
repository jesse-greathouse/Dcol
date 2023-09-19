<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\AiTrainingFile;

use Dcol\Assistant\OpenAi\File as FileApi;

class TrainingFileSync extends Command
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
    protected $signature = 'dcol:trainingfile:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes AI Training File Data';

    /**
     * Maps Entity Keys to Api Object Keys
     * 
     * @var array
     */
    protected $map = [
        'ai_id'             => 'id',
        'bytes'             => 'bytes',
        'ai_created_at'     => 'created_at',
        'status'            => 'status',
        'filename'          => 'filename',
        'object'            => 'object',
        'purpose'           => 'purpose',
        'status_details'    => 'status_details'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $recordFileCount = $this->getAiTrainingFilesCount();
        
        if ($recordFileCount < 1) {
            if ($this->isVerbose()) {
                $this->info("No Training Files to synchronize.");
                $this->newLine();
            }
            exit(0);
        }

        $fileApi = new FileApi($this->getAuthKey());
        $res = $fileApi->list();
        $data = $res->json();

        if (!isset($data['data'])) {
            $this->error('Invalid Response from OpenAI Api');
            $this->newLine();
            exit(1);
        }

        $liveFileList = $this->makeLiveFileIndex($data['data']);
        $keys = array_keys($liveFileList);
        $liveFileCount = count($keys);

        if ($this->isVerbose()) {
            $this->info("Found $liveFileCount Files to sync.");
            $this->info("Comparison against $recordFileCount Files records.");
            $this->newLine();
        }

        foreach($this->getAiTrainingFiles() as $aiTrainingFile) {
            $id = $aiTrainingFile->ai_id;

            if (!in_array($id, $keys)) {
                if ($this->isVerbose()) {
                    $this->line("File with id: $id not found in API response");
                    $this->line("deleting...");
                    $this->newLine();
                }
                $aiTrainingFile->delete();
                continue;
            }

            foreach($this->map as $modelKey => $apiKey) {
                if (isset($liveFileList[$id][$apiKey])) {
                    $aiTrainingFile->{$modelKey} = $liveFileList[$id][$apiKey];
                }
            }

            $aiTrainingFile->save();
        }

        if ($this->isVerbose()) {
            $this->info("Finished Syncing Training Files");
            $this->newLine();
        }
    }

    /**
     * Turns an API response into a dictionary indexed by file id
     *
     * @param array $data
     * @return array
     */
    protected function makeLiveFileIndex($data): array
    {
        $list = [];
        $keys = array_values($this->map);
        foreach($data as $element) {
            $id = $element['id'];
            foreach($keys as $key) {
                $list[$id][$key] = $element[$key];
            }
        }

        return $list;
    }

    /**
     * Returns a collection of published Blog Posts for the blog
     *
     * @return Collection
     */
    protected function getAiTrainingFiles(): Collection
    {
        return $this->getTrainingFilesQb()->orderBy('ai_training_files.ai_created_at', 'DESC')->get([
            'ai_training_files.ai_id',
            'ai_training_files.bytes',
            'ai_training_files.filename',
            'ai_training_files.ai_created_at',
            'ai_training_files.status',
            'ai_training_files.object',
            'ai_training_files.purpose',
            'ai_training_files.status_details',
        ]);
    }

    /**
     * Returns the number of published Blog Posts for the blog.
     *
     * @return int
     */
    protected function getAiTrainingFilesCount(): int
    {
        return $this->getTrainingFilesQb()->count();
    }

    /**
     * Returns a querybuilder with the eligible blog posts query.
     *
     * @return Builder
     */
    protected function getTrainingFilesQb(): Builder
    {

        $qb = AiTrainingFile::leftJoin('ai_models', function($join) {
                $join->on('ai_training_files.id', '=', 'ai_models.ai_training_file_id');
            })
            ->whereNull('ai_models.ai_training_file_id');

        $qb = $qb->where(function (Builder $query) {
            $query->where('ai_training_files.status', AiTrainingFile::STATUS_UPLOADED)
                ->orWhere('ai_training_files.status', AiTrainingFile::STATUS_ERROR)
                ->orWhere('ai_training_files.status', AiTrainingFile::STATUS_PROCESSED)
                ->orWhere('ai_training_files.status', AiTrainingFile::STATUS_PENDING);

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
