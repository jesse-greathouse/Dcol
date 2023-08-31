<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\Blog,
    App\Models\BlogPost,
    App\Models\Site,
    Dcol\Blog\Post\Manager;


class MonitorPublication extends Command
{
    /**
     * Blog selected for run
     *
     * @var Blog
     */
    protected $blog;

    /**
     * Site selected for run
     *
     * @var Site
     */
    protected $site;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dcol:monitorpublication {iterations=0} {--blog=} {--site=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queries uunpublished Blog Posts to update their publish status.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        # CLI arguments
        $iterations = (int)$this->argument('iterations');
        $numTweets = (int)$this->options('num_tweets');

        $this->getBlog();
        $this->getSite();

        if (0 === $iterations) {
            $iterations = $this->getDynamicIterations();
        }

        for ($i=0; $i < $iterations; $i++) {
            $blogPost = $this->getBlogPost();

            if (null === $blogPost) {
                $this->line('No blog posts qualified to have status updated.');
                $this->newLine(2);
                break;
            }

            # Lock the BlogPost so it can't be serviced by concurrent jobs.
            $this->lock($blogPost);
            $this->info("Blog Post {$blogPost->post_id}: \"{$blogPost->slug}\"");
            $this->newLine();

            $manager = new Manager(
                $blogPost->blog, $blogPost->document, $this->getVarDir('blog'), $this->getTmpDir('blog'), $blogPost->document->uri
            );

            // Check to see if the Blog Post has been published.
            try {
                $blogPost = $manager->syncBlogPost($blogPost);
            } catch (\Exception $e) {
                $this->unLock($blogPost);
                $this->error($e->getMessage());
                continue;
            }

            // If the synced blog post is published, save its current status.
            if ($blogPost->is_published) {
                $blogPost->save();
                $this->info("{$blogPost->post_id} status updated.");
            } else {
                # Update the timestamps to push it down the order to be processed.
                $blogPost->touch();
                $this->info("{$blogPost->post_id} is not yet published.");
            }

            $this->unLock($blogPost);
            $this->newLine(2);
        }
    }

    /**
     * Figures out iterations dynamically if the user didnt provide to the CLI.
     * 
     * @return integer
     */
    protected function getDynamicIterations(): int 
    {
        return $this->getBlogPostQb()->count();
    }

    /**
     * Gets a Blog Post to be Tweeted.
     * Qualifies the selection by input options.
     *
     * @return Document|null
     */
    protected function getBlogPost(): BlogPost|null
    {
        return $this->getBlogPostQb()->orderBy('updated_at')->first([
            'blog_posts.id',
            'blog_posts.created_at',
            'blog_posts.updated_at',
            'blog_posts.post_id',
            'blog_posts.slug',
            'blog_posts.url',
            'blog_posts.author',
            'blog_posts.publication_date',
            'blog_posts.type',
            'blog_posts.title',
            'blog_posts.content',
            'blog_posts.category',
            'blog_posts.featured_media',
            'blog_posts.is_published',
            'blog_posts.is_tweeted',
            'blog_posts.blog_id',
            'blog_posts.document_id',
            'blog_posts.is_locked'
        ]);
    }

    /**
     * Instantiates a query builder dynamically given the various inputs to this job.
     * Necessary for selecting documents relevant to this run.
     *
     * @return Builder
     */
    protected function getBlogPostQb(): Builder
    {
        $site = $this->getSite();
        $blog = $this->getBlog();

        if (null !== $site) {
            $qb = $this->getBlogPostBySiteQb();
        } else if (null !== $blog) {
            $qb = $this->getBlogPostByBlogQb();
        } else {
            $qb = BlogPost::query();
        }

        $qb = $qb->where('blog_posts.is_published', false)
            ->where('blog_posts.is_locked', false);

        return $qb;
    }

    /**
     * Instantiates a query builder dynamically given the various inputs to this job.
     * Necessary for selecting documents relevant to this run.
     *
     * @return Builder
     */
    protected function getBlogPostBySiteQb(): Builder
    {
        $site = $this->getSite();
        $blog = $this->getBlog();

        $qb = BlogPost::join('documents', 'documents.id', '=', 'blog_posts.document_id')
            ->join('pages', 'pages.id', '=', 'documents.page_id')
            ->join('sites', 'sites.id', '=', 'pages.site_id')
            ->join('blog_site', 'blog_site.site_id', '=', 'sites.id')
            ->where('sites.id', $site->id);

        if (null !== $blog) {
            $qb = $qb->where('blog_site.blog_id', $blog->id);
        }

        return $qb;
    }


    /**
     * Instantiates a query builder dynamically given the various inputs to this job.
     * Necessary for selecting documents relevant to this run.
     *
     * @return Builder
     */
    protected function getBlogPostByBlogQb(): Builder
    {
        $blog = $this->getBlog();
        return BlogPost::where('blog_id', $blog->id);
    }

    /**
     * Get blog selected for run
     *
     * @return  Blog|null
     */ 
    protected function getBlog(): Blog|null
    {
        if (null === $this->blog) {
            $blogDomain = $this->option('blog');
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
     * Get site selected for run
     *
     * @return  Site|null
     */ 
    protected function getSite(): Site|null
    {
        if (null === $this->site) {
            $siteDomain = $this->option('site');
            if (null !== $siteDomain) {
                $this->site = Site::where('domain_name', $siteDomain)->first();

                if (null === $this->site) {
                    $this->error("Site with domain name: \"$siteDomain\" was not found.");
                    exit(1);
                }
                
                # If a blog was included in the command,
                # validate there is a connection between the blog and this site.
                $blog = $this->getBlog();
                if (null !== $blog) {
                    $validated = false;

                    foreach ($blog->sites as $blogSite) {
                        if ($blogSite->domain_name === $this->site->domain_name) {
                            $validated = true;
                            break;
                        }
                    }

                    if (!$validated) {
                        $this->error("Blog: \"{$blog->domain_name}\" does not have site: \"$siteDomain\".");
                        exit(1);
                    }
                }
            }
        }

        return $this->site;
    }

    /**
     * Locks the BlogPost
     *
     * @param BlogPost $blogPost
     * @return void
     */
    protected function lock(BlogPost $blogPost): void
    {
        BlogPost::updateOrCreate(
            ['id' => $blogPost->id],
            ['is_locked' => true]
        );
    }

    /**
     * Unlocks the BlogPost
     *
     * @param BlogPost $blogPost
     * @return void
     */
    protected function unLock(BlogPost $blogPost): void
    {
        $blogPost->is_locked = false;
        $blogPost->save();
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
