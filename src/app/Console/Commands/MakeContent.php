<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\Response;


use Dcol\Assistant\OpenAi\ChatCompletion,
    Dcol\Assistant\Request\OpenAiRequest,
    Dcol\Content\Manager;

use App\Models\Author,
    App\Models\Document,
    App\Models\Content,
    App\Models\Tag;

/**
 * Command to make the content from a document.
 */
class MakeContent extends Command
{
    /**
     * Authorization secret for OpenAI
     *
     * @var string
     */
    protected $authKey;

    /**
     * The directory which holds temporary files
     *
     * @var string
     */
    protected $tmpDir;

    /**
     * The directory which holds the var files
     *
     * @var string
     */
    protected $varDir;

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
        # Get Directories for working with the content of this document
        $varDir = $this->getVarDir();
        $tmpDir = $this->getTmpDir();

        # CLI arguments
        $iterations = (int)$this->argument('iterations');
        $uri = $this->option('uri');
        $regress = $this->option('regress');

        if (null !== $uri) {
            $iterations = 1;
        } elseif (0 === $iterations) {
            $iterations = $this->getDynamicIterations($regress);
        }

        for ($i=0; $i < $iterations; $i++) {
            $document = $this->getDocument($uri, $regress);

            if (null === $document) {
                $this->line('No Documents qualified to make content.');
                $this->newLine(2);
                break;
            }

            $this->info("Making content for document: \"{$document->uri}\"");

            # Update the timestamps to push it down the order to be processed.
            $document->touch();

            # Instantiate the ChatCompletion Object.
            $chat = new ChatCompletion($this->getAuthKey());

            # Instantiate the content Manager.
            $manager = new Manager($chat, $varDir, $tmpDir, $document->uri);

            # Create the content.
            try {
                $content = $manager->createContent($document->decodeRawText());
            } catch(\Exception $e) {
                $this->error($e->getMessage());
                continue;
            }

            # Separate the authors and tags objects from the content.
            $authors = $content['authors'];
            unset($content['authors']);

            $tags = $content['tags'];
            unset($content['tags']);

            # Persist the content
            $c = Content::updateOrCreate(['document_id' => $document->id], $content);

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

            # Unset to clear memory.
            unset($document);
            unset($chat);
            unset($manager);
            unset($content);
            unset($authors);
            unset($tags);
            unset($c);
            unset($a);
            unset($t);

            $this->newLine(2);
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
        })->orderBy('updated_at');
    }

    /**
     * Returns the value of the system "var" dir
     *
     * @return string
     */
    private function getVarDir(): string 
    {
        if (null === $this->varDir) {
            $sessSavePath = ini_get('session.save_path');
            $parts = explode('/', $sessSavePath);
            # remove the last folder
            array_pop($parts);
            $contentsFolder = implode('/', $parts) . '/contents';
            if (!is_dir($contentsFolder)) {
                // dir doesn't exist, make it
                mkdir($contentsFolder);
            }
            $this->varDir = $contentsFolder;
        }
    
        return $this->varDir;
    }

    /**
     * Get the directory which holds temporary files
     *
     * @return  string
     */ 
    public function getTmpDir(): string
    {
        if (null === $this->tmpDir) {
            $contentsFolder = sys_get_temp_dir() . '/contents';
            if (!is_dir($contentsFolder)) {
                // dir doesn't exist, make it
                mkdir($contentsFolder);
            }
            $this->tmpDir = $contentsFolder;
        }

        return $this->tmpDir;
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
