<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\AiTrainingFile,
    App\Models\Blog;

class TrainingFileList extends Command
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
    protected $signature = 'dcol:trainingfile:list {blog}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists all the Training Files for a Blog';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $blog = $this->getBlog();
        
        $this->info("Listing Training Files For: \"{$blog->domain_name}\" ");
        $this->newLine();

        $count = $this->getTrainingFilesCount();

        if ($count > 0) {
            [$headers, $body] = $this->makeTable($this->getTrainingFiles());
            $this->table($headers, $body);
        } else {
            $this->info("No Training Files found.");
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
        $headers = ['Blog', 'ID', 'filename', 'Created At', 'Status', 'Status Details'];
        $body = [];
        
        forEach($rs as $trainingFile) {
            try {
                $date = new \DateTime();
                $date->setTimestamp($trainingFile->ai_created_at);
                $dateStr = $date->format(self::CREATED_AT_FORMAT);
            } catch(\Exception $e) {
                $dateStr = $trainingFile->ai_created_at;
            }

            $body[] = [
                $blog->domain_name,
                $trainingFile->ai_id,
                $trainingFile->filename,
                $dateStr,
                $trainingFile->status,
                $trainingFile->status_details,
            ];
        }

        return [$headers, $body];
    }

    /**
     * Returns a collection of published Blog Posts for the blog
     *
     * @return Collection
     */
    protected function getTrainingFiles(): Collection
    {
        return $this->getTrainingFilesQb()->orderBy('ai_training_files.ai_created_at', 'DESC')->get([
            'ai_training_files.ai_id',
            'ai_training_files.filename',
            'ai_training_files.ai_created_at',
            'ai_training_files.status',
            'ai_training_files.status_details',
        ]);
    }

    /**
     * Returns the number of published Blog Posts for the blog.
     *
     * @return int
     */
    protected function getTrainingFilesCount(): int
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
        $blog = $this->getBlog();

        $qb = AiTrainingFile::leftJoin('ai_models', function($join) {
                $join->on('ai_training_files.id', '=', 'ai_models.ai_training_file_id');
            })
            ->whereNull('ai_models.ai_training_file_id')
            ->where('ai_training_files.blog_id', $blog->id);

        $qb = $qb->where(function (Builder $query) {
            $query->where('ai_training_files.status', AiTrainingFile::STATUS_UPLOADED)
                ->orWhere('ai_training_files.status', AiTrainingFile::STATUS_PROCESSED)
                ->orWhere('ai_training_files.status', AiTrainingFile::STATUS_PENDING);

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
