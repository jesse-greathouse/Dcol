<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\Response;


use Dcol\Assistant\OpenAi\ChatCompletion,
    Dcol\Assistant\Request\OpenAiRequest,
    Dcol\Content\Manager;

use App\Models\Author,
    App\Models\Blog,
    App\Models\Document,
    App\Models\Content,
    App\Models\Tag;

/**
 * Command to make the content from a document.
 */
class MakeContent extends Command
{
    use OutputCheck, PrependsOutput, PrependsTimestamp;

    const PACKET_ORDER_FAULT = 'Packets out of order.';

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
    protected $signature = 'dcol:makecontent {iterations=0} {--uri=} {--regress}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Produces content from collected documents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        # CLI arguments
        $iterations = (int)$this->argument('iterations');
        $uri = $this->option('uri');
        $regress = $this->option('regress');
        $model = null;

        if (null !== $uri) {
            $iterations = 1;
        } elseif (0 === $iterations) {
            $iterations = $this->getDynamicIterations($regress);
        }

        for ($i=0; $i < $iterations; $i++) {
            $document = $this->getDocument($uri, $regress);

            if (null === $document) {
                if ($this->isVerbose()) {
                    $this->line('No Documents qualified to make content.');
                    $this->newLine();
                }
                break;
            }

            if ($this->isVerbose()) {
                $this->info("Making content for document: \"{$document->uri}\"");
            }

            # Update the timestamps to push it down the order to be processed.
            $document->touch();

            // Create content in the context of a blog.
            foreach($this->getBlogsForDocument($document) as $blog) {
                 # Get Directories for working with the content of this document
                $varDir = $this->getVarDir($blog->domain_name);
                $tmpDir = $this->getTmpDir($blog->domain_name);

                $aiModel = null;
                $aiPersona = null;
                if (null !== $blog->aiModel) {
                    $aiModel = $blog->aiModel->fine_tuned_model;
                    $aiPersona = $blog->ai_assistant_persona;
                }

                # Instantiate the ChatCompletion Object.
                $chat = new ChatCompletion($this->getAuthKey(), $aiModel, $aiPersona);

                # Instantiate the content Manager.
                $manager = new Manager($chat, $varDir, $tmpDir, $document->uri);

                # Create the content.
                try {
                    $raw = $document->decodeRawText();
                    $content = $manager->createContent($raw);
                } catch(\Exception $e) {
                    $this->error($e->getMessage());
                    continue;
                }

                # Separate the authors and tags objects from the content.
                $authors = $content['authors'];
                unset($content['authors']);

                $tags = $content['tags'];
                unset($content['tags']);

                try {
                    # Persist the content
                    $c = Content::updateOrCreate(['document_id' => $document->id], $content);
                } catch(\Exception $e) {
                    $message = $e->getMessage();
                    // Disable documents with content that causes database insert problems.
                    if (false !== strpos($message, self::PACKET_ORDER_FAULT)) {
                        $message = "Database Error: " . self::PACKET_ORDER_FAULT;
                        $document->active = false;
                        $document->save();
                    }

                    $this->error("Unable to save content from document: {$document->id} \"{$document->uri}\"");
                    $this->error($message);
                    continue;
                }

                # Persist the associated authors
                foreach($authors as $author) {
                    $a = Author::updateOrCreate(
                        ['name' => $author, 'document_id' => $document->id]
                    );
                }

                # Persist the associated tags
                foreach($tags as $tag) {
                    $t = Tag::updateOrCreate(
                        ['value' => $tag, 'document_id' => $document->id]
                    );
                }

                unset($chat);
                unset($manager);
                unset($content);
                unset($authors);
                unset($tags);
                unset($c);
                unset($a);
                unset($t);
            }

            # Unset to clear memory.
            unset($document);
        }
    }

    /**
     * Builds a Query for finding documents
     *
     * @param string|null $uri
     * @param bool $regress
     * @return Document|null
     */
    private function getDocument(string|null $uri, bool $regress): Document|null
    {
        if (null === $uri) {
            # If the command is called with --regress, then query all documents
            # Otherwise only query documents with no content
            if ($regress) {
                return $this->getRegressQb()->first();
            } else {
                return $this->getNonRegressQb()->first([
                    'documents.id',
                    'documents.created_at',
                    'documents.updated_at',
                    'documents.url',
                    'documents.file_name',
                    'documents.uri',
                    'documents.raw_text',
                    'documents.type_id',
                    'documents.page_id'
                ]);
            }
        } else {
            $document = Document::where('uri', $uri)->first();

            if (null === $document) {
                $this->error("Document with the uri: \"$uri\" was not found.");
            }

            return $document;
        }
    }

    /**
     * Gets a Recordset of all the blogs for a certain document.
     * 
     * @param Document $document
     * @return Collection
     */
    private function getBlogsForDocument(Document $document): Collection
    {
        $qb =  Blog::join('blog_site', 'blogs.id', '=', 'blog_site.blog_id')
            ->join('sites', 'sites.id', '=', 'blog_site.site_id')
            ->join('pages', 'pages.site_id', '=', 'sites.id')
            ->join('documents', 'documents.page_id', '=', 'pages.id')
            ->where('documents.id', $document->id);
            
        $blogs =  $qb->get([
            'blogs.id',
            'blogs.created_at',
            'blogs.updated_at',
            'blogs.domain_name',
            'blogs.username',
            'blogs.password',
            'blogs.ai_model_id',
            'blogs.ai_assistant_persona',
        ]);

        return Blog::hydrate($blogs->toArray());
    }

    /**
     * Figures out iterations dynamically if the user didnt provide to the CLI.
     *
     * @param boolean $regress
     * @return integer
     */
    private function getDynamicIterations(bool $regress): int
    {
        if ($regress) {
            $qb = $this->getRegressQb();
        } else {
            $qb = $this->getNonRegressQb();
        }

        return $qb->count();
    }

    /**
     * Returns a QueryBuilder for when the user did not flag for a regression.
     *
     * @return Builder
     */
    private function getNonRegressQb(): Builder
    {
        return Document::leftJoin('contents', function($join) {
            $join->on('documents.id', '=', 'contents.document_id');
        })
        ->where('documents.active', true)
        ->whereNull('contents.document_id')
        ->whereNot(function (Builder $query) {
            $query->where('documents.raw_text', '')
                ->orWhere('documents.raw_text', null);
        })
        ->orderBy('documents.updated_at');
    }

    /**
     * Returns a QueryBuilder for when the user did flag for a regression.
     *
     * @return Builder
     */
    private function getRegressQb(): Builder
    {
        return Document::whereNot(function (Builder $query) {
            $query->where('raw_text', '')
                ->orWhere('raw_text', null);
        })
        ->where('active', true)
        ->orderBy('updated_at');
    }

    /**
     * Returns the value of the system "var" dir
     *
     * @param  string $blogName
     * @return string
     */
    private function getVarDir(string $blogName): string 
    {
        $sessSavePath = ini_get('session.save_path');
        $parts = explode('/', $sessSavePath);
        array_pop($parts);
        return $this->getContentDir(implode('/', $parts), $blogName);
    }

    /**
     * Get the directory which holds temporary files
     * 
     * @param   string $blogName
     * @return  string
     */ 
    public function getTmpDir(string $blogName): string
    {
        return $this->getContentDir(sys_get_temp_dir(), $blogName);
    }

    /**
     * Get the directory which holds the files
     * 
     * @param   string $baseDir
     * @param   string $contentDir
     * @return  string
     */ 
    private function getContentDir(string $baseDir, string $contentDir): string
    {
        $contentsFolder = "$baseDir/contents";
        if (!is_dir($contentsFolder)) {
            // dir doesn't exist, make it
            mkdir($contentsFolder);
        }

        $blogContentFolder = "$contentsFolder/$contentDir";
        if (!is_dir($blogContentFolder)) {
            // dir doesn't exist, make it
            mkdir($blogContentFolder);
        }

        return $blogContentFolder;
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
