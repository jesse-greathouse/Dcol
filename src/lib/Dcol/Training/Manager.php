<?php

namespace Dcol\Training;

use Illuminate\Http\Client\Response;
use Illuminate\Database\Eloquent\Collection;

use Dcol\AbstractManager,
    Dcol\Content\Manager as ContentManager,
    Dcol\Assistant\OpenAi\ChatCompletion,
    Dcol\Assistant\Request\OpenAiRequest;

use App\Models\Blog,
    App\Models\BlogPost,
    App\Models\Content;

class Manager extends AbstractManager
{
    const FILE_EXT='jsonl';

    /**
     * Blog record.
     *
     * @var Blog
     */
    protected $blog;

    /**
     * Constructor.
     *
     * @param Blog $blog
     * @param string $cacheDir
     * @param string $tmpDir
     * @param string $uri
     */
    public function __construct(Blog $blog, string $baseCacheDir, string $baseTmpDir)
    {
        $this->setBlog($blog);
        $this->setCacheDir($baseCacheDir);
        $this->setTmpDir($baseTmpDir);
        $this->setFileExtension(Manager::FILE_EXT);
    }

    public function writeTrainingFileLine(array $line, string $suffix, bool $firstLine = false)
    {
        ['type' => $type, 'system' => $system, 'user' => $user, 'assistant' => $assistant] = $line;
        $row = [
            'messages' => [
                [
                    'role'      => 'system',
                    'content'   => "You are $system",
                ],
                [
                    'role'      => 'user',
                    'content'   => $user,
                ],
                [
                    'role'      => 'assistant',
                    'content'   => $assistant,
                ],
            ]
        ];

        try {
            $txt = '';

            if (!$firstLine) {
                $txt .= "\n";
            }
            
            $txt .= json_encode($row, JSON_INVALID_UTF8_SUBSTITUTE);
            
        } catch (\Exception $e) {
            throw new \Exception("unable to encode json for: " . print_r($line) . "\n\n" . $e->getMessage());
        }

        $this->writeCacheLine($txt, $suffix);
    }

    public function createTrainingFile(array $lines, string $suffix ) 
    {
        $job = '';

        foreach($lines as $line) {
            ['type' => $type, 'system' => $system, 'user' => $user, 'assistant' => $assistant] = $line;

            $row = [
                'messages' => [
                    [
                        'role'      => 'system',
                        'content'   => "You are $system",
                    ],
                    [
                        'role'      => 'user',
                        'content'   => $user,
                    ],
                    [
                        'role'      => 'assistant',
                        'content'   => $assistant,
                    ],
                ]
            ];

            try {
                $txt = json_encode($row, JSON_THROW_ON_ERROR);
                $job .= "$txt\n";
            } catch (\Exception $e) {
                // don't do anything.
            }
        }

        $job = rtrim($job);

        $this->setCache($job, $suffix);
    }

    /**
     * Get blog record.
     *
     * @return  Blog
     */ 
    public function getBlog()
    {
        return $this->blog;
    }

    /**
     * Set blog record.
     *
     * @param  Blog  $blog  Blog record.
     *
     * @return  self
     */ 
    public function setBlog(Blog $blog)
    {
        $this->blog = $blog;

        return $this;
    }

    /**
     * Set directory for holding cached files.
     *
     * @param  string  $cacheDir  Directory for holding cached files.
     *
     * @return  self
     */ 
    public function setCacheDir(string $cacheDir): AbstractManager
    {
        $blog = $this->getBlog();
        $dir = $cacheDir .  '/' . $blog->domain_name;
        if (!is_dir($dir)) {
            $this->buildTrainingDir($cacheDir, $uri);
        }

        $this->cacheDir = $dir;

        return $this;
    }

    /**
     * Set directory for holding temporary files.
     *
     * @param  string  $tmpDir  Directory for holding temporary files.
     *
     * @return  self
     */ 
    public function setTmpDir(string $tmpDir): AbstractManager
    {
        $blog = $this->getBlog();
        $dir = $tmpDir .  '/' . $blog->domain_name;
        if (!is_dir($dir)) {
            $this->buildTrainingDir($tmpDir, $uri);
        }

        $this->tmpDir = $dir;

        return $this;
    }

    /**
     * Builds a directory from a specified path
     *
     * @param string $baseDir
     * @param string $uri
     * @return void
     */
    protected function buildTrainingDir(string $baseDir, string $uri): void
    {
        $parts = explode('/', $uri);
        
        # Create the Directory Structure if it doesn't currently exist.
        $buildDir = $baseDir;
        foreach($parts as $dir) {
            $buildDir .= '/' . $dir;
            if (!is_dir($buildDir)) {
                // dir doesn't exist, make it
                mkdir($buildDir);
            }
        }
    }
}
