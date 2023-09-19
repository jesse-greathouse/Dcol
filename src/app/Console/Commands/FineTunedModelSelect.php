<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\AiModel,
    App\Models\Blog;

class FineTunedModelSelect extends Command
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
    protected $signature = 'dcol:finetunedmodel:select {blog}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign an Ai Model to a Blog';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $blog = $this->getBlog();
        
        $this->info("Select an Ai Models For: \"{$blog->domain_name}\" ");
        $this->newLine();

        $count = $this->getAiModelsCount();

        if ($count > 0) {
            $options = [];
            $optionsIndex = [];
    
            foreach($this->getAiModels() as $aiModel) {
                $date = new \DateTime();
                $date->setTimestamp($aiModel->ai_created_at);
                $dateStr = $date->format(self::CREATED_AT_FORMAT);
                $label = sprintf('%s -- %s', $dateStr, $aiModel->fine_tuned_model);
                $options[] = $label;
                $optionsIndex[$label] = $aiModel->id;
            }
    
            $selection = $this->choice(
                "Which Fine Tuned Model Should be used for: \"{$blog->domain_name}\"?",
                $options
            );
    
            $blog->ai_model_id = $optionsIndex[$selection];
            $blog->save();
            $this->info("{$blog->domain_name} is now using Fine Tuned Model: $selection.");

        } else {
            $this->info("No fine Tuned Ai Models availble for:{$blog->domain_name}.");
            $this->newLine();
        }
    }


    /**
     * Returns a collection of published Blog Posts for the blog
     *
     * @return Collection
     */
    protected function getAiModels(): Collection
    {
        return $this->getAiModelsQb()->orderBy('ai_models.ai_created_at', 'DESC')->get([
            'ai_models.id',
            'ai_models.fine_tuned_model',
            'ai_models.ai_created_at',
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
            ->where('ai_training_files.blog_id', $blog->id)
            ->where('ai_models.status', AiModel::STATUS_SUCCEEDED);

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
