<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\Blog,
    App\Models\BlogPost,
    App\Models\Content;

use Dcol\Blog\Post\Manager as BlogPostManager,
    Dcol\Content\Manager as ContentManager,
    Dcol\Training\Manager as TrainingManager;

class TrainAssistant extends Command
{
    const REGEX_AFTER_H2 = '/(?<=<\/h2>)(?s)(.*$)/';

    const REGEX_BEFORE_H2 = '/(.*)(?=<h2.*>)/si';

    const REGEX_REMOVE_CTA_BANNER = '/<div\s+class="cta-banner .*">([\s\S]*?)<\/a><\/p>/';

    const REGEX_REMOVE_LATEST_POSTS = '#<ul\s+class="wp-block-latest-posts__list wp-block-latest-posts">([\s\S]*?)</ul>#';

    protected $contentMap = [
        'title'             => ContentManager::TYPE_TITLE,
        'meta_description'  => ContentManager::TYPE_META_DESCRIPTION,
        'writeup'           => ContentManager::TYPE_WRITEUP,
        'blurb'             => ContentManager::TYPE_BLURB,
    ];

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
    protected $signature = 'dcol:trainassistant {blog}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trains AI assistant to make better content.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        # CLI arguments
        $blog = $this->getBlog();
        $count = $this->getBlogPostsCount();
        $persona = $blog->ai_assistant_persona;

        $this->info("Training AI For: \"{$blog->domain_name}\" -- Found $count eligible blog posts to train.");
        $this->newLine();

        $errors = [];
        $lines = [];

        foreach($this->getBlogPosts() as $blogPost) {

            $blurb = $this->getBlurbFromBlogPost($blogPost);
            $writeup = $this->getWriteupFromBlogPost($blogPost);
            $postedContent = [
                ContentManager::TYPE_TITLE              => $blogPost->title,
                ContentManager::TYPE_META_DESCRIPTION   => $blogPost->meta_description,
                ContentManager::TYPE_WRITEUP            => $writeup,
                ContentManager::TYPE_BLURB              => $blurb,
            ];

        }

        # Unlock the document if everything was successful.
        # If there were errors, then keep it locked and display the exceptions.
        foreach($errors as $e) {
            $this->error($e->getMessage());
        }
    }

    protected function getBlurbFromBlogPost(BlogPost $blogPost)
    {
        $matches = [];
        $count = preg_match(self::REGEX_BEFORE_H2, $blogPost->content, $matches);
        $content = $matches[1];
        $cleaned = preg_replace(self::REGEX_REMOVE_CTA_BANNER, '', $content);
        $cleaned = str_replace("<p> </p>", "\n\n", $cleaned);
        $cleaned = str_replace("</p>\n<p>", "\n\n", $cleaned);
        $cleaned = trim(strip_tags($cleaned));
        return $cleaned;
    }

    protected function getWriteupFromBlogPost(BlogPost $blogPost)
    {
        $matches = [];
        preg_match(self::REGEX_AFTER_H2, $blogPost->content, $matches);
        $cleaned = preg_replace(self::REGEX_REMOVE_LATEST_POSTS, '', $matches[1]);
        $cleaned = str_replace("</p>\n<p>", "\n\n", $cleaned);
        $cleaned = trim(strip_tags($cleaned));
        return $cleaned;
    }

    /**
     * Returns a collection of published Blog Posts for the blog
     *
     * @return Collection
     */
    protected function getBlogPosts(): Collection
    {
        return $this->getBlogPostsQb()->get();
    }

    /**
     * Returns the number of published Blog Posts for the blog.
     *
     * @return int
     */
    protected function getBlogPostsCount(): int
    {
        return $this->getBlogPostsQb()->count();
    }

    /**
     * Returns a querybuilder with the eligible blog posts query.
     *
     * @return Builder
     */
    protected function getBlogPostsQb(): Builder
    {
        $blog = $this->getBlog();

        return BlogPost::where('blog_id', $blog->id)
                        ->where('is_published', true)
                        ->whereNot(function (Builder $query) {
                            $query->where('meta_description', '')
                                ->orWhere('meta_description', null);
                        })
                        ->whereNot(function (Builder $query) {
                            $query->where('focus_keyphrase', '')
                                ->orWhere('focus_keyphrase', null);
                        });
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
     * Returns the value of the system "var" dir
     *
     * @return string
     */
    private function getVarDir($type): string 
    {
        $sessSavePath = ini_get('session.save_path');
        $parts = explode('/', $sessSavePath);
        # remove the last folder
        array_pop($parts);
        $dir = implode('/', $parts) . "/$type";
        if (!is_dir($dir)) {
            // dir doesn't exist, make it
            mkdir($dir);
        }
    
        return $dir;
    }

    /**
     * Get the directory which holds temporary files
     *
     * @return  string
     */ 
    public function getTmpDir($type): string
    {
        $dir = sys_get_temp_dir() . "/$type";
        if (!is_dir($dir)) {
            // dir doesn't exist, make it
            mkdir($dir);
        }

        return $dir;
    }
}
