<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\AiModel,
    App\Models\Blog;

class FineTunedModelList extends Command
{
    use OutputCheck, PrependsOutput, PrependsTimestamp;

    const CREATED_AT_FORMAT = 'm/d/Y H:i:s';

    /**
     * Blog selected for run
     *
     * @var Blog
     */
    protected $blog;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dcol:finetunedmodel:list {blog}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists all the models for a Blog';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $blog = $this->getBlog();
        
        $this->info("Listing Ai Models For: \"{$blog->domain_name}\" ");
        $this->newLine();

        $count = $this->getAiModelsCount();

        if ($count > 0) {
            [$headers, $body] = $this->makeTable($this->getAiModels());
            $this->table($headers, $body);
        } else {
            $this->info("No Ai Models found.");
            $this->newLine();
        }
    }

    /**
     * Takes Query results and turns it into an array formatted for the table display.
     *
     * @param Collection $rs
     * @return array
     */
    protected function makeTable(Collection $rs): array {
        $blog = $this->getBlog();
        $headers = ['Blog', 'ID', 'Fine Tuned Model', 'Created At', 'Status', 'Training File'];
        $body = [];
        
        forEach($rs as $aiModel) {
            try {
                $date = new \DateTime();
                $date->setTimestamp($aiModel->ai_created_at);
                $dateStr = $date->format(self::CREATED_AT_FORMAT);
            } catch(\Exception $e) {
                $dateStr = $aiModel->ai_created_at;
            }

            $body[] = [
                $blog->domain_name,
                $aiModel->ai_id,
                $aiModel->fine_tuned_model,
                $dateStr,
                $aiModel->status,
                $aiModel->training_file,
            ];
        }

        return [$headers, $body];
    }

    /**
     * Returns a collection of published Blog Posts for the blog
     *
     * @return Collection
     */
    protected function getAiModels(): Collection
    {
        return $this->getAiModelsQb()->orderBy('ai_models.ai_created_at', 'DESC')->get([
            'ai_models.ai_id',
            'ai_models.fine_tuned_model',
            'ai_models.ai_created_at',
            'ai_models.status',
            'ai_models.training_file',
        ]);
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
     * Returns a querybuilder with the eligible blog posts query.
     *
     * @return Builder
     */
    protected function getAiModelsQb(): Builder
    {
        $blog = $this->getBlog();

        $qb = AiModel::join('ai_training_files', 'ai_models.ai_training_file_id', '=', 'ai_training_files.id')
            ->where('ai_models.active', 1)
            ->where('ai_training_files.blog_id', $blog->id);

        $qb = $qb->where(function (Builder $query) {
            $query->where('ai_models.status', AiModel::STATUS_CREATED)
                ->orWhere('ai_models.status', AiModel::STATUS_PENDING)
                ->orWhere('ai_models.status', AiModel::STATUS_RUNNING)
                ->orWhere('ai_models.status', AiModel::STATUS_SUCCEEDED);

        });

        return $qb;
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
}
