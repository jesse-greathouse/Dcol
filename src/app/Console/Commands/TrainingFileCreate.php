<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use App\Models\AiTrainingFile,
    App\Models\Blog,
    App\Models\BlogPost,
    App\Models\Content;

use Dcol\Assistant\OpenAi\File as FileApi,
    Dcol\Assistant\OpenAi\ChatCompletion,
    Dcol\Assistant\OpenAi\Tokenizer,
    Dcol\Content\Manager as ContentManager,
    Dcol\Training\Manager as TrainingManager;

class TrainingFileCreate extends Command
{
    use OutputCheck, PrependsOutput, PrependsTimestamp;

    const MAX_TOKENS_PER_MESSAGE = 2000;

    const DEFAULT_SEO_SCORE_THRESHOLD = 70;

    const REGEX_AFTER_H2 = '/(?<=<\/h2>)(?s)(.*$)/';

    const REGEX_BEFORE_H2 = '/(.*)(?=<h2.*>)/si';

    const REGEX_REMOVE_CTA_BANNER = '/<div\s+class="cta-banner .*">([\s\S]*?)<\/a><\/p>/';

    const REGEX_REMOVE_LATEST_POSTS = '#<ul\s+class="wp-block-latest-posts__list wp-block-latest-posts">([\s\S]*?)</ul>#';

    /**
     * Content Types
     *
     * @var array
     */
    protected $contentTypes = [
        ContentManager::TYPE_TITLE,
        ContentManager::TYPE_META_DESCRIPTION,
        ContentManager::TYPE_WRITEUP,
        ContentManager::TYPE_BLURB,
    ];

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
     * The SEO score threshold by which to select blog posts.
     *
     * @var int
     */
    protected $seoScoreThreshold;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dcol:trainingfile:create {blog} {--seo-score-threshold=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates an AI training File';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        # CLI arguments
        $blog = $this->getBlog();
        $count = $this->getBlogPostsCount();
        $persona = $blog->ai_assistant_persona;
        $suffix = $this->getSuffix();

        # Get Directories for working with the content of this document
        $varDir = $this->getVarDir('training');
        $tmpDir = $this->getTmpDir('training');
        
        $this->info("Creating AI training file For: \"{$blog->domain_name}\" -- Found $count eligible blog posts to train.");
        $this->newLine();

        $manager = new TrainingManager($blog, $varDir, $tmpDir);
        $firstLine = true;

        foreach($this->getBlogPosts() as $blogPost) {
            // Get the original Content Prompts
            $contentPrompts = $this->getContentPrompts($blogPost);

            // Get the posted Blog Content
            $postedContent = $this->getPostedContent($blogPost);

            foreach($this->contentTypes as $type) {
                // If the content is unuseable don't use it to train the AI model.
                if (null === $contentPrompts[$type] || null === $postedContent[$type]) {
                    continue;
                }

                $line = $this->getContentLine($type, $persona, $contentPrompts[$type], $postedContent[$type]);
                $manager->writeTrainingFileLine($line, $suffix, $firstLine);
                $firstLine = false;
                // clearing memory
                $line = null;
            }

            $contentPrompts = null;
            $postedContent = null;
        }
        
        $path = $manager->getCacheFile($suffix);

        if ($this->isVerbose()) {
            $this->info("Training file created at: \"$path\".");
        }

        $fileApi = new FileApi($this->getAuthKey(), $path, AiTrainingFile::PURPOSE);
        $res = $fileApi->upload();
        $data = $res->json();
        $aiTrainingFile = AiTrainingFile::factory()->make([
            'ai_id'             => $data['id'],
            'bytes'             => $data['bytes'],
            'ai_created_at'     => $data['created_at'],
            'filename'          => $data['filename'],
            'status'            => $data['status'],
            'uri'               => $path,
            'object'            => $data['object'],
            'purpose'           => $data['purpose'],
            'status_details'    => $data['status_details'],
            'blog_id'           => $blog->id,
        ]);
        $aiTrainingFile->save();

        $this->info("Ai Training File: \"{$aiTrainingFile->ai_id}\" was created. Currenly it is in status: \"{$aiTrainingFile->status}\". ");
        $this->newLine();

        if (unlink($path)) {
            if ($this->isVerbose()) {
                $this->info("Training file: \"$path\" deleted.");
            }
        } else {
            $this->error("Unable to delete file at: \"$path\" ");
        };
    }

    protected function getContentPrompts(BlogPost $blogPost): array
    {
        // Setup Content Manager For Blog Post Context
        $document = $blogPost->document;
        $contentsVarDir = $this->getVarDir("contents/{$blogPost->blog->domain_name}");
        $contentsTmpDir = $this->getTmpDir("contents/{$blogPost->blog->domain_name}");
        $uri = $document->uri;
        $contentManager = new ContentManager(new ChatCompletion($this->getAuthKey()), $contentsVarDir, $contentsTmpDir, $uri);
        $prompts = $contentManager->getPrompts();

        // Retrieve cached content
        $raw = $this->filterUnicode($contentManager->getCache(ContentManager::TYPE_RAW));
        $rawTruncated = $this->firstNWords($raw);
        $cleanedWriteup = $this->filterUnicode($contentManager->getCache(ContentManager::TYPE_WRITEUP));
        $writeUpTruncated = $contentManager->truncateByTokens($cleanedWriteup);

        // Create Prompts For content
        $title = $this->getContentPromptByType($prompts[ContentManager::TYPE_TITLE](), $writeUpTruncated);
        $blurb = $this->getContentPromptByType($prompts[ContentManager::TYPE_BLURB]($blogPost->title,), $writeUpTruncated);
        $metaDescription = $this->getContentPromptByType($prompts[ContentManager::TYPE_META_DESCRIPTION]($blogPost->title,), $writeUpTruncated);
        $writeUp = $this->getContentPromptByType($prompts[ContentManager::TYPE_WRITEUP](), $rawTruncated);

        return [
            ContentManager::TYPE_TITLE              => $title,
            ContentManager::TYPE_META_DESCRIPTION   => $metaDescription,
            ContentManager::TYPE_WRITEUP            => $writeUp,
            ContentManager::TYPE_BLURB              => $blurb,
        ];
    }

    protected function firstNwords($s, $limit = 900)
    {
        $pieces = explode(" ", $s);
        return implode(" ", array_splice($pieces, 0, $limit)); 
    }

    /**
     * Converts Unicode strings into utf-8
     *
     * @param string $text
     * @return string
     */
    protected function filterUnicode(string $text): string {
        return html_entity_decode(preg_replace("/U\+([0-9A-F]{4})/s", "&#x\\1;", $text), ENT_NOQUOTES, 'UTF-8');
    }

    protected function filterTabs(string $text): string
    {
        return trim(preg_replace('/\t+/', '', $text));
    }

    protected function filterNewlines(string $text): string
    {
        return trim(preg_replace('/\n+/', '', $text));
    }

    protected function getPostedContent(BlogPost $blogPost): array
    {
        $blurb = $this->getBlurbFromBlogPost($blogPost);
        $writeup = $this->getWriteupFromBlogPost($blogPost);

        return [
            ContentManager::TYPE_TITLE              => $blogPost->title,
            ContentManager::TYPE_META_DESCRIPTION   => $blogPost->meta_description,
            ContentManager::TYPE_WRITEUP            => $writeup,
            ContentManager::TYPE_BLURB              => $blurb,
        ];
    }

    protected function getContentLine(string $type, string $persona, string $user, string $assistant): array
    {
        return [
            'type'      => $type,
            'system'    => $persona,
            'user'      => $user,
            'assistant' => $assistant,
        ];
    }

    protected function getContentPromptByType(string $prompt, string $text): string
    {
        $cleaned = $this->filterTabs($text);
        $cleaned = $this->filterNewlines($cleaned);
        $contentPrompt = "$prompt\n\n$cleaned";

        return $this->truncateByTokens($contentPrompt);
    }

    protected function getBlurbFromBlogPost(BlogPost $blogPost)
    {
        $matches = [];
        $count = preg_match(self::REGEX_BEFORE_H2, $blogPost->content, $matches);
        if (!isset($matches[1])) { 
            return null; 
        }
        $content = $matches[1];
        $cleaned = preg_replace(self::REGEX_REMOVE_CTA_BANNER, '', $content);
        $cleaned = str_replace("<p> </p>", "\n\n", $cleaned);
        $cleaned = str_replace("</p>\n<p>", "\n\n", $cleaned);
        $cleaned = trim(strip_tags($cleaned));
        $cleaned = $this->filterTabs($cleaned);
        
        return $this->truncateByTokens($cleaned);
    }

    protected function getWriteupFromBlogPost(BlogPost $blogPost)
    {
        $matches = [];
        preg_match(self::REGEX_AFTER_H2, $blogPost->content, $matches);
        $cleaned = preg_replace(self::REGEX_REMOVE_LATEST_POSTS, '', $matches[1]);
        $cleaned = str_replace("</p>\n<p>", "\n\n", $cleaned);
        $cleaned = trim(strip_tags($cleaned));
        $cleaned = $this->filterTabs($cleaned);
        
        return $this->truncateByTokens($cleaned);
    }

    /**
     * Take a content string and Truncate it by the number of tokens.
     *
     * @param string $content
     * @param integer|null $numTokens
     * @return string
     */
    protected function truncateByTokens(string $content, int $numTokens = null): string
    {
        if (null === $numTokens) {
            $numTokens = self::MAX_TOKENS_PER_MESSAGE;
        }

        $tokens = Tokenizer::encode($content);
        $truncated = array_slice($tokens, 0, $numTokens);
        $output = Tokenizer::decode($truncated);
        $truncated = null;

        return $output;
    }

    /**
     * Returns a collection of published Blog Posts for the blog
     *
     * @return Collection
     */
    protected function getBlogPosts(): Collection
    {
        return $this->getBlogPostsQb()->get([
            'blog_posts.id',
            'blog_posts.post_id',
            'blog_posts.slug',
            'blog_posts.url',
            'blog_posts.author',
            'blog_posts.publication_date',
            'blog_posts.type',
            'blog_posts.title',
            'blog_posts.meta_description',
            'blog_posts.focus_keyphrase',
            'blog_posts.content',
            'blog_posts.category',
            'blog_posts.featured_media',
            'blog_posts.is_published',
            'blog_posts.is_tweeted',
            'blog_posts.is_ai_model_trained',
            'blog_posts.blog_id',
            'blog_posts.document_id',
            'blog_posts.is_locked',
            'blog_posts.seo_score',
        ]);
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
        $seoScoreThreshold = $this->getSeoScoreThreshold();

        return BlogPost::where('blog_id', $blog->id)
                        ->join('documents', 'documents.id', '=', 'blog_posts.document_id')
                        ->where('documents.active', true)
                        ->where('blog_posts.is_published', true)
                        ->where('blog_posts.seo_score', '>=', $seoScoreThreshold)
                        ->whereNot(function (Builder $query) {
                            $query->where('blog_posts.meta_description', '')
                                ->orWhere('blog_posts.meta_description', null);
                        })
                        ->whereNot(function (Builder $query) {
                            $query->where('blog_posts.focus_keyphrase', '')
                                ->orWhere('blog_posts.focus_keyphrase', null);
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

    /**
     * Set a string that uniquely identifies a new training session
     *
     * @param  string  $suffix  A string that uniquely identifies a new training session
     *
     * @return  self
     */ 
    public function setSuffix(string $suffix)
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Get seoScoreThreshold
     *
     * @return  int
     */ 
    protected function getSeoScoreThreshold(): int
    {
        if (null === $this->seoScoreThreshold) {
            $seoScoreThreshold = $this->option('seo-score-threshold');
            if (null !== $seoScoreThreshold) {
                $this->seoScoreThreshold = (int) $seoScoreThreshold;
            } else {
                return self::DEFAULT_SEO_SCORE_THRESHOLD;
            }
        }

        return $this->seoScoreThreshold;
    }
}
