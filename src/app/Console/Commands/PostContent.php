<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\Blog,
    App\Models\BlogMedia,
    App\Models\BlogPost,
    App\Models\Document,
    App\Models\DocumentLock,
    App\Models\Site;

use Dcol\Blog\Post\Manager as BlogPostManager,
    Dcol\Image\Generator as ImageGenerator,
    Dcol\Image\Manager as ImageManager;

class PostContent extends Command
{
    use OutputCheck, PrependsOutput, PrependsTimestamp;

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
    protected $signature = 'dcol:postcontent {iterations=0} {--blog=} {--site=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Posts content to blogs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        # CLI arguments
        $iterations = (int)$this->argument('iterations');
        $this->getBlog();
        $this->getSite();

        if (0 === $iterations) {
            $iterations = $this->getDynamicIterations();
        }

        for ($i=0; $i < $iterations; $i++) {
            $document = $this->getDocument();
            $errors = [];

            if (null === $document) {
                if ($this->isVerbose()) {
                    $this->line('No document qualified to be posted.');
                    $this->newLine(2);
                }
                break;
            }

            # Lock the Document so it can't be serviced by concurrent jobs.
            $lock = DocumentLock::updateOrCreate(
                ['document_id' => $document->id],
                ['is_locked' => true]
            );

            if ($this->isVerbose()) {
                $this->info("Document: \"{$document->uri}\"");
                $this->newLine();
            }

            foreach($this->getBlogsForDocument($document) as $blog) {
                if ($this->isVerbose()) {
                    $this->info("Posting content to: \"{$blog->domain_name}\"");
                    $this->newLine();
                }

                $postManager = new BlogPostManager(
                    $blog, $document, $this->getVarDir('blog'), $this->getTmpDir('blog'), $document->uri
                );

                $imageManager = new ImageManager(
                    new ImageGenerator(), $document, $this->getVarDir('image'), $this->getTmpDir('image'), $document->uri
                );
                
                // Upload the Document to the Blog.
                try {
                    $blogMediaDocument = BlogMedia::where('blog_id', $blog->id)
                        ->where('document_id', $document->id)
                        ->where('mime_type', 'application/pdf')
                        ->first();

                    if (null === $blogMediaDocument) {
                        # Upload the document to the blog.
                        $blogMediaDocument = $postManager->uploadDocument();
                        $blogMediaDocument->save();
                        if ($this->isVerbose()) {
                            $this->info("Uploaded document {$blogMediaDocument->media_id}: \"{$blogMediaDocument->slug}\"");
                            $this->newLine();
                        }
                    }
                } catch(\Exception $e) {
                    $errors[] = $e;
                    continue;
                }

                // Create the Featured Image and Upload it to the blog.
                try {
                    $blogMediaFeaturedImage = BlogMedia::where('blog_id', $blog->id)
                        ->where('document_id', $document->id)
                        ->where('is_featured_image', true)
                        ->first();

                    if (null === $blogMediaFeaturedImage) {
                        # Download the default image template for the Blog+Site
                        $backgroundImage = $postManager->downloadDefaultFeaturedMediaImage();
                        if ($this->isVerbose()) {
                            $this->info("Downloaded Default image for background:");
                            $this->line($backgroundImage);
                        }

                        # Create the featured Image for the Blog Post.
                        $featuredImage = $imageManager->createFeaturedimage($backgroundImage);
                        if ($this->isVerbose()) {
                            $this->info("Composed featured image:");
                            $this->line($featuredImage);
                        }

                        # Upload the featured image
                        $blogMediaFeaturedImage = $postManager->uploadFile($featuredImage);
                        $blogMediaFeaturedImage->is_featured_image = true;
                        $blogMediaFeaturedImage->save();
                        if ($this->isVerbose()) {
                            $this->info("Uploaded image {$blogMediaFeaturedImage->media_id}: \"{$blogMediaFeaturedImage->slug}\"");
                            $this->newLine();
                        }
                    }
                } catch(\Exception $e) {
                    $errors[] = $e;
                    continue;
                }

                // Create the Blog Post.
                try {
                    $blogPost = BlogPost::where('blog_id', $blog->id)
                        ->where('document_id', $document->id)
                        ->first();

                    if (null === $blogPost) {
                        $blogPost = $postManager->makePost($blogMediaDocument, $blogMediaFeaturedImage);
                        $blogPost->save();
                        if ($this->isVerbose()) {
                            $this->info("Created Blog Post {$blogPost->post_id}: \"{$blogPost->slug}\"");
                            $this->newLine();
                        }
                    }

                    # Save the association between the new blog post and its media.
                    $blogPost->blogMedia()->save($blogMediaDocument);
                    $blogPost->blogMedia()->save($blogMediaFeaturedImage);
                } catch(\Exception $e) {
                    $errors[] = $e;
                    continue;
                }

                # Unset these things to clear memory.
                unset($postManager);
                unset($imageManager);
                unset($blogMediaDocument);
                unset($blogMediaFeaturedImage);
                unset($blogPost);
            }

            # Unlock the document if everything was successful.
            # If there were errors, then keep it locked and display the exceptions.
            if (count($errors) < 1 ) {
                $lock->is_locked = false;
                $lock->save();
            } else {
                foreach($errors as $e) {
                    $this->error($e->getMessage());
                }
            }

            unset($lock);
        }
    }

    /**
     * Gets a document to be posted.
     * Qualifies the selection by input options.
     *
     * @return Document|null
     */
    protected function getDocument(): Document|null
    {
        $qb = $this->getDocumentQb();

        return $qb->first([
            'documents.id',
            'documents.created_at',
            'documents.updated_at',
            'documents.url',
            'documents.file_name',
            'documents.uri',
            'documents.raw_text',
            'documents.type_id',
            'documents.page_id',
            'documents.active',
        ]);
    }

    /**
     * Figures out iterations dynamically if the user didnt provide to the CLI.
     * 
     * @return integer
     */
    protected function getDynamicIterations(): int 
    {
        $qb = $this->getDocumentQb();
        return $qb->count();
    }

    /**
     * Instantiates a query builder dynamically given the various inputs to this job.
     * Necessary for selecting documents relevant to this run.
     *
     * @return Builder
     */
    protected function getDocumentQb(): Builder
    {
        $blog = $this->getBlog();
        $site = $this->getSite();

        /**
         * Select the first of a list of documents which has no associated blog post records
         * Site and Blog are possible modifiers to narrow the selection
         * Query builder makes a big and scary query which is meant to produce something like this:
         * 
         * select * 
         * from `documents` 
         * inner join `pages` on `pages`.`id` = `documents`.`page_id` 
         * inner join `sites` on `sites`.`id` = `pages`.`site_id` 
         * inner join `contents` on `contents`.`document_id` = `documents`.`id` 
         * inner join `blog_site` on `sites`.`id` = `blog_site`.`site_id` 
         * inner join `blogs` on `blogs`.`id` = `blog_site`.`blog_id` 
         * left join `blog_posts` on `documents`.`id` = `blog_posts`.`document_id` 
         * where `blog_posts`.`document_id` is null 
         * and `sites`.`id` = 1 
         * and `blogs`.`id` = 1 
         * and not (`documents`.`raw_text` = '' or `documents`.`raw_text` is null);
        */

        $qb = Document::join('pages', 'pages.id', '=', 'documents.page_id')
            ->join('sites', 'sites.id', '=', 'pages.site_id')
            ->join('contents', 'contents.document_id', '=', 'documents.id');

        if (null !== $blog) {
            $qb = $qb->join('blog_site', 'sites.id', '=', 'blog_site.site_id')
                ->join('blogs', 'blogs.id', '=', 'blog_site.blog_id');
        }

        $qb = $qb->leftJoin('document_locks', function($join) {
            $join->on('documents.id', '=', 'document_locks.document_id');
        });
        
        $qb = $qb->leftJoin('blog_posts', function($join) {
            $join->on('documents.id', '=', 'blog_posts.document_id');
        })
        ->whereNull('blog_posts.document_id');

        if (null !== $site) {
            $qb = $qb->where('sites.id', $site->id);
        }

        if (null !== $blog) {
            $qb = $qb->where('blogs.id', $blog->id);
        }

        $qb = $qb->whereNot(function (Builder $query) {
            $query->where('documents.raw_text', '')
                ->orWhere('documents.raw_text', null);
        });

        $qb = $qb->where(function (Builder $query) {
            $query->where('document_locks.is_locked', '=', false)
                ->orWhere('document_locks.is_locked', null);
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
     * Returns a Collection of jobs that are relevant to the document.
     *
     * @return Collection
     */
    protected function getBlogsForDocument(Document $document): Collection
    {
        $blog = $this->getBlog();
        $site = $this->getSite();

        $qb =  Blog::join('blog_site', 'blogs.id', '=', 'blog_site.blog_id')
            ->join('sites', 'sites.id', '=', 'blog_site.site_id')
            ->join('pages', 'pages.site_id', '=', 'sites.id')
            ->join('documents', 'documents.page_id', '=', 'pages.id')
            ->where('documents.id', $document->id);
            
        if (null !== $site) {
            $qb = $qb->where('sites.id', $site->id);
        }

        if (null !== $blog) {
            $qb = $qb->where('blogs.id', $blog->id);
        }
            
        return $qb->get([
            'blogs.id',
            'blogs.created_at',
            'blogs.updated_at',
            'blogs.domain_name',
            'blogs.username',
            'blogs.password'
        ]);
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
