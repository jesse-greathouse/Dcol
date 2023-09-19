<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

use App\Models\Document,
    App\Models\Page,
    App\Models\Site,
    App\Models\Type;

use App\Exceptions\DocumentDownloadException,
    App\Exceptions\DocumentDownload400Exception,
    App\Exceptions\DocumentDownload401Exception,
    App\Exceptions\DocumentDownload403Exception,
    App\Exceptions\DocumentDownload404Exception;

use Dcol\Parsers\PdfParser;

/**
 * Command to crawl pages and find documents for adding to the document table
 */
class Crawl extends Command
{
    use OutputCheck, PrependsOutput, PrependsTimestamp;

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
    protected $signature = 'dcol:crawl {iterations=0} {--site=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Surfs through the collection of pages registered in Dcol and performs the assigned selections of content.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        # Build the Query
        $qb = $this->getQb($this->argument('iterations'));

        foreach ($qb->get() as $page) {
            if ($this->isVerbose()) {
                $this->info("Crawling page: \"{$page->url}\"");
            }

            # Update the currently cralwed page so it won't be crawled first on the next run.
            $page->updated_at = new \DateTime();
            $page->save();

            # Read the page and get the Body of the content.
            $response = Http::withRequestMiddleware(
                function (RequestInterface $request) {
                    return $request->withHeader('X-Example', 'Value');
                }
            )->get($page->url);

            $body = $response->body();

            foreach($page->selectors as $selector) {
                $s = new $selector->class;
                foreach($s->select([$body], $page->url)->getSelections() as $selection) {
                    $downloadUrl = $this->urlFilter($selection, $page);
                    $fileUri = $this->getUriFromUrl($downloadUrl);
                    $baseName = basename($fileUri);

                    # Check and see if the file has already been downloaded
                    # Download the file if it does not currently exist in the file system.
                    $downloadedFile = $this->getVarDir() . '/' . $fileUri;
                    if (file_exists($downloadedFile)) {
                        if ($this->isVerbose()) {
                            $this->newLine();
                            $this->line("$baseName already exists... skipping download");
                        }
                    } else {
                        try {
                            $downloadedFile = $this->downloadFile($downloadUrl);
                        } catch(DocumentDownloadException $e) {
                            $reflect = new \ReflectionClass($e);
                            $this->error($reflect->getShortName() . ' : ' . $downloadUrl);
                            continue;
                        }
                    }

                    # Check and see if the document already exists in the document table
                    $document = Document::where('uri', $fileUri)->first();
                    if (NULL === $document) {
                        $parser = new PdfParser($downloadedFile);
                        $type = Type::where('type_name', PdfParser::TYPE_NAME)->first();
                        try {
                            $document = Document::factory()->create([
                                'url'       => $downloadUrl,
                                'file_name' => $baseName,
                                'uri'       => $fileUri,
                                'raw_text'  => $parser->parse(),
                                'type_id'   => $type->id,
                                'page_id'   => $page->id,
                            ]);
                        } catch(\Exception $e) {
                            $msg = $e->getMessage();
                            // Check to see if it's a secured pdf exception.
                            // We don't currently have the ability to parse a secured pdf.
                            if (false === strpos($msg, 'Secured pdf file are currently not supported')) {
                                $this->error($msg);
                            }
                        } catch(\TypeError $e ) {
                            $this->error($e);
                        }
                        unset($parser);
                    }
                    unset($document);
                }
            }
        }
    }

    /**
     * Builds a Query for pages to crawl
     *
     * @param integer $limit
     * @return Builder
     */
    private function getQb(int $limit): Builder
    {
        $qb = Page::whereNot(function (Builder $query) {
            $query->where('url', '')
                ->orWhere('url', null);
        });

        $site = $this->getSite();
        if (null !== $site) {
            $qb = $qb->where('site_id', $site->id);
        }
        
        
        $qb = $qb->orderBy('updated_at');


        if ($limit) {
            $qb = $qb->take($limit);
        }

        return $qb;
    }

    /**
     * Filters a selected file path into a downloadable url
     *
     * @param string $path
     * @param Page $page
     * @return string
     */
    private function urlFilter(string $path, Page $page): string
    {
        if (str_starts_with($path, 'www')) {
            return 'https://' . $path;
        }

        if (str_starts_with($path, '//')) {
            return 'https:' . $path;
        }

        if (str_starts_with($path, '/')) {
            return 'https://' . $page->site->domain_name . $path;
        }

        if (str_starts_with($path, '../')) {
            $path =  $this->pathRegressorFilter($path, $page->url);
            return 'https://' . $page->site->domain_name . $path;
        }

        return $this->sameDirectoryFilter($page->url) . '/' . $path;
    }

    /**
     * Calculates a new path based on the original url and a series of path regressors: '../'
     *
     * @param string $path
     * @param string $url
     * @return string
     */
    private function pathRegressorFilter(string $path, string $url): string
    {
        $regressorCount = substr_count($path, '../');
        $pathParts = explode('/', $path);
        $urlParts = explode('/', $url);

        # remove the last nth elements according to the series of regressors
        $exUrlParts = array_slice($urlParts, 4, (count($urlParts) - (4 + $regressorCount)));

        # remove the first nth elements according to the series of regressors
        $exPathParts = array_slice($pathParts, $regressorCount);

        array_push($exUrlParts, ...$exPathParts);

        return '/'. implode('/', $exUrlParts);
    }

    /**
     * Filters a URL to serve from the present directory if the last element is a file
     * e.g.: file.aspx, index.php, etc...
     *
     * @param string $url
     * @return string
     */
    private function sameDirectoryFilter(string $url): string
    {
        $parts = explode('/', $url);
        $file = array_pop($parts);
        if (false === strpos($file, '.')) {
            array_push($parts, $file);
        }

        return implode('/', $parts);
    }

    /**
     * Downloads a file from a url
     *
     * @param string $url
     * @return string|null
     */
    private function downloadFile(string $url): string|null
    {
        // Use basename() function to return the base name of file
        $tmpPath = $this->tmpFilePathFilter($url);
        $varPath = NULL;

        if ($this->isVerbose()) {
            $this->line("Downloading file from: $url");
        }

        try {
            # Download to tmp Folder
            file_put_contents($tmpPath, file_get_contents($url));

            # Move to var Folder
            $varPath = $this->moveToVarDir($tmpPath, $url);
        } catch(\ErrorException $e) {
            $message = $e->getMessage();

            if (false !== strpos($message, '404 Not Found')) {
                throw new DocumentDownload404Exception($message);
            } elseif (false !== strpos($message, '403 Forbidden')) {
                throw new DocumentDownload403Exception($message);
            }  elseif (false !== strpos($message, '401 Unauthorized')) {
                throw new DocumentDownload401Exception($message);
            }  elseif (false !== strpos($message, '400 Bad Request')) {
                throw new DocumentDownload400Exception($message);
            } else {
                throw new DocumentDownloadException($message);
            }
        }

        return $varPath;
    }

    /**
     * Takes a URL and returns the corresponding system temp dir for storing a download
     *
     * @param string $url
     * @return string
     */
    private function tmpFilePathFilter(string $url): string
    {
        $tmpDir = $this->getTmpDir();
        $documentsFolder = $tmpDir . '/documents';
        if (!is_dir($documentsFolder)) {
            // dir doesn't exist, make it
            mkdir($documentsFolder);
        }
        $path = $this->getUriFromUrl($url);
        $this->buildDirFromPath($documentsFolder, $path);

        return $documentsFolder . '/' . $path;
    }

    /**
     * Moves a file to the system var dir based on the url
     *
     * @param string $from
     * @param string $url
     * @return string
     */
    private function moveToVarDir(string $from, string $url): string 
    {
        $varDir = $this->getVarDir();
        $path = $this->getUriFromUrl($url);
        $this->buildDirFromPath($varDir, $path);
        $to = $varDir . '/' . $path;
        try {
            rename($from, $to);
        } catch(\Exception $e) {
            $this->error($e->getMessage());
        }

        return $to;
    }

    /**
     * Builds a directory from a specified path
     *
     * @param string $baseDir
     * @param string $path
     * @return void
     */
    private function buildDirFromPath(string $baseDir, string $path): void
    {
        $parts = explode('/', $path);
        $fileName = basename($path);
        
        # Create the Directory Structure if it doesn't currently exist.
        $buildDir = $baseDir;
        foreach($parts as $dir) {
            if ($dir === $fileName) {
                continue;
            } else {
                $buildDir .= '/' . $dir;
                if (!is_dir($buildDir)) {
                    // dir doesn't exist, make it
                    mkdir($buildDir);
                }
            }
        }
    }

    /**
     * Takes a url and returns a path
     *
     * @param string $url
     * @return string
     */
    private function getUriFromUrl(string $url): string 
    {
        $parts = explode('/', $url);
        unset($parts[0]);
        unset($parts[1]);
        $path = implode('/', $parts);
        return $path;
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
            }
        }

        return $this->site;
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
            $documentsFolder = implode('/', $parts) . '/documents';
            if (!is_dir($documentsFolder)) {
                // dir doesn't exist, make it
                mkdir($documentsFolder);
            }
            $this->varDir = $documentsFolder;
        }
    
        return $this->varDir;
    }

    /**
     * Get the directory which holds temporary files
     *
     * @return  string
     */ 
    public function getTmpDir()
    {
        if (null === $this->tmpDir) {
            $this->tmpDir = sys_get_temp_dir();
        }

        return $this->tmpDir;
    }
}
