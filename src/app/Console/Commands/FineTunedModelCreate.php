<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\AiModel,
    App\Models\AiTrainingFile,
    App\Models\Blog,
    App\Models\BlogPost,
    App\Models\Content;

use Dcol\Assistant\OpenAi\File as FileApi,
    Dcol\Assistant\OpenAi\FineTuning as FineTuningApi,
    Dcol\Blog\Post\Manager as BlogPostManager,
    Dcol\Content\Manager as ContentManager,
    Dcol\Training\Manager as TrainingManager;

class FineTunedModelCreate extends Command
{
    use OutputCheck, PrependsOutput, PrependsTimestamp;

    const CREATED_AT_FORMAT = 'm/d/Y H:i:s';

    /**
     * A string that uniquely identifies a new training session
     * 
     * @var string
     */
    protected $suffix;

    /**
     * Authorization secret for OpenAI
     *
     * @var string
     */
    protected $authKey;

    /**
     * Blog selected for run
     *
     * @var Blog
     */
    protected $blog;

    /**
     * Ai Training File
     *
     * @var AiTrainingFile
     */
    protected $file;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dcol:finetunedmodel:create {blog} {--file=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a Fine Tuned Model';

    /**
     * Training File Fields.
     *
     * @var array
     */
    protected $trainingFileFields = [
        'ai_training_files.id',
        'ai_training_files.created_at',
        'ai_training_files.updated_at',
        'ai_training_files.ai_id',
        'ai_training_files.bytes',
        'ai_training_files.ai_created_at',
        'ai_training_files.filename',
        'ai_training_files.status',
        'ai_training_files.uri',
        'ai_training_files.object',
        'ai_training_files.purpose',
        'ai_training_files.status_details',
        'ai_training_files.blog_id'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        # CLI arguments
        $blog = $this->getBlog();
        $file = $this->getFile();
        $suffix = $this->getSuffix();
        
        $this->info("You are about to create a new model for: \"{$blog->domain_name}\" with file: \"{$file->ai_id}\"");
        $this->newLine();

        // Confirm agreement to create the model
        if (false === $this->confirm('Proceed?', true)) {
            $this->line('Cancelling...');
            $this->newLine();
            exit(0);
        } else {
            $this->info("Starting new model job...");
        }

        $fineTuningApi = new FineTuningApi($this->getAuthKey(), $file->ai_id, $suffix);
        $res = $fineTuningApi->create();
        $data = $res->json();

        $aiModel = AiModel::factory()->make([
            'ai_id'                 => $data['id'],
            'ai_created_at'         => $data['created_at'],
            'ai_finished_at'        => $data['finished_at'],
            'model'                 => $data['model'],
            'fine_tuned_model'      => $data['fine_tuned_model'],
            'status'                => $data['status'],
            'result_files'          => $this->jsonField('result_files', $data),
            'trained_tokens'        => $data['trained_tokens'],
            'error'                 => $data['error'],
            'object'                => $data['object'],
            'organization_id'       => $data['organization_id'],
            'training_file'         => $data['training_file'],
            'validation_file'       => $data['validation_file'],
            'hyperparameters'       => $this->jsonField('hyperparameters', $data),
            'ai_training_file_id'   => $file->id,
        ]);
        $aiModel->save();

        $this->info("Job for new Ai Model was created. Currenly it is in status: \"{$aiModel->status}\". ");
    }

    /**
     * Get blog selected for run
     *
     * @return  Blog|null
     */ 
    protected function getBlog(): Blog|null
    {
        if (null === $this->blog) {
            $blogDomain = $this->argument('blog');
            if (null !== $blogDomain) {
                $this->blog = Blog::where('domain_name', $blogDomain)->first();
                if (null === $this->blog) {
                    $this->error("Blog with domain name: \"$blogDomain\" was not found.");
                    exit(1);
                }
            }
        }

        return $this->blog;
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
     * Get ai Training File
     *
     * @return  AiTrainingFile
     */ 
    public function getFile()
    {
        if (null === $this->file) {
            $blog = $this->getBlog();
            $fileId = $this->option('file');
            if (null === $fileId) {
                $fileId = $this->promptFile();
            }

            $qb = $this->getTrainingFileQb();

            $this->file = $qb->where('ai_training_files.ai_id', $fileId)->first($this->trainingFileFields);

            if (null === $this->file) {
                $this->error("File with identifier: \"$fileId\" was not found or not useable with: {$blog->domain_name}.");
                exit(1);
            }
        }

        return $this->file;
    }

    /**
     * prompts the user for command line input to select file
     * 
     * @return string
     */
    private function promptFile(): string
    {
        $blog = $this->getBlog();
        $qb = $this->getTrainingFileQb();
        $count = $qb->count();

        # No files exist.
        if ($count < 1) {
            $this->error("No training files exist for: {$blog->domain_name}.");
            $this->error("Try creating one with: php artisan dcol:trainingfile:create {$blog->domain_name}");
            $this->newLine();
            exit(1);
        }

        # One file exists.
        if ($count === 1) {
            $aiTrainingFile = $qb->first($this->trainingFileFields);
            return $aiTrainingFile->ai_id;
        }

        $options = [];
        $optionsIndex = [];
        $files = $qb->orderBy('ai_created_at', 'DESC')->get($this->trainingFileFields);

        foreach($files as $file) {
            $date = new \DateTime();
            $date->setTimestamp($file->ai_created_at);
            $dateStr = $date->format(self::CREATED_AT_FORMAT);
            $label = sprintf('%s    --  %s', $file->ai_id, $dateStr);
            $options[] = $label;
            $optionsIndex[$label] = $file->ai_id;
        }

        $selection = $this->choice(
            'Which Training File Should Be Used For This Model?',
            $options,
            0
        );

        return $optionsIndex[$selection];
    }

    private function getTrainingFileQb(): Builder
    {
        $blog = $this->getBlog();
        // Select ai Training Files for this blog, which are not currently attached to a model.
        return AiTrainingFile::leftJoin('ai_models', function($join) {
                $join->on('ai_training_files.id', '=', 'ai_models.ai_training_file_id');
            })
            ->whereNull('ai_models.ai_training_file_id')
            ->where('ai_training_files.blog_id', $blog->id)
            ->where('ai_training_files.status', AiTrainingFile::STATUS_PROCESSED);
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

    /**
     * Get a string that uniquely identifies a new training session
     *
     * @return  string
     */ 
    public function getSuffix()
    {
        if (null === $this->suffix) {
            $this->suffix = bin2hex(random_bytes(8));
        }

        return $this->suffix;
    }
}
