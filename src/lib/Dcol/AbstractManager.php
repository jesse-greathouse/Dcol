<?php

namespace Dcol;

/**
 * Abstract operation manager class
 */
abstract class AbstractManager {

    /**
     * Directory for holding cached files.
     *
     * @var string
     */
    protected $cacheDir;

    /**
     * Directory for holding temporary files.
     *
     * @var string
     */
    protected $tmpDir;

    /**
     * The URI of the document.
     * 
     * @var string
     */
    protected $uri;

    /**
     * File Extension for cached/tmp files.
     *
     * @var string
     */
    protected $fileExtension='';

    /**
     * Takes the partially collected content and subtracts part of the array to resume collecting.
     *
     * @param array $paragraphs
     * @param string $content
     * @return array
     */
    protected function resumeContentMultipart(array $paragraphs, string $content): array {
        # Number of paragraphs in content
        $contentParagraphs = $this->getParagraphs($content);

        # Number of missing paragraphs in content
        $diff = count($paragraphs) - count($contentParagraphs);

        # Convert the difference into an array position
        $offset = $diff - 1;

        return array_slice($paragraphs, $offset);
    }

    /**
     * Chunk the content into paragraphs to make things manageable.
     *
     * @param string $content
     * @return array
     */
    protected function getParagraphs(string $content): array
    {
        $pattern = "/(?s)((?:[^\n][\n]?)+)/im";
        $matches = [];

        preg_match_all($pattern, $content, $matches);

        if (!isset($matches[0])) {
            throw new \Exception("Unable to break content into paragraphs");
        } else {
            $paragraphs = $matches[0];
        }

        return $paragraphs;
    }

    /**
     * Builds a directory from a specified path
     *
     * @param string $baseDir
     * @param string $path
     * @return void
     */
    protected function buildDirFromUri(string $baseDir): void
    {
        $parts = explode('/', $this->getUri());
        
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

    /**
     * Returns content if content is cached. Returns false if cache doesn't exist.
     *
     * @param string $fileName
     * @return string|false
     */
    protected function getCache(string $fileName): string|false
    {
        $file = $this->getCacheDir() . '/' . $fileName . '.' . $this->getFileExtension();
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== '') {
                return $content;
            }
        }

        return false;
    }

    /**
     * Writes the content to the given file cache by type
     *
     * @param string $content|null
     * @param string $fileName
     * @return string|false
     */
    protected function setCache(string|null $content, string $fileName): void
    {
        $file = $this->getCacheDir() . '/' . $fileName . '.' . $this->getFileExtension();
        $bytes = file_put_contents($file, $content);
        if (false === $bytes) {
            throw new \Exception("Unable to write cache to \"$file\"");
        }
    }

    /**
     * Removes a cache file
     *
     * @param string $fileName
     * @return void
     */
    protected function removeCache(string $fileName): void
    {
        if (false !== $this->getCache($fileName)) {
            $file = $this->getCacheDir() . '/' . $fileName . '.' . $this->getFileExtension();
            unlink($file);
        }
    }

    /**
     * Returns content of temporary file. Returns false if file doesn't exist.
     *
     * @param string $fileName
     * @return string|false
     */
    protected function getTmp(string $fileName): string|false
    {
        $file = $this->getTmpDir() . '/' . $fileName . '.' . $this->getFileExtension();
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== '') {
                return $content;
            }
        }

        return false;
    }

    /**
     * Writes the content to the given temporary file by type
     *
     * @param string $content
     * @param string $fileName
     * @param boolean $append
     * @return string|false
     */
    protected function setTmp(string $content, string $fileName, $append = false): void
    {
        $file = $this->getTmpDir() . '/' . $fileName . '.' . $this->getFileExtension();
        $appendFlag = ($append) ? FILE_APPEND : 0;
        $bytes = file_put_contents($file, $content, $appendFlag);
        if (false === $bytes) {
            throw new \Exception("Unable to write to temporary file \"$file\"");
        }
    }

    /**
     * Removes a temporary file
     *
     * @param string $fileName
     * @return void
     */
    protected function removeTmp(string $fileName): void
    {
        if (false !== $this->getTmp($fileName)) {
            $file = $this->getTmpDir() . '/' . $fileName . '.' . $this->getFileExtension();
            unlink($file);
        }
    }

    /**
     * Get directory for holding cached files.
     *
     * @return  string
     */ 
    public function getCacheDir(): string
    {
        return $this->cacheDir;
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
        $dir = $cacheDir .  '/' . $this->getUri();
        if (!is_dir($dir)) {
            $this->buildDirFromUri($cacheDir);
        }

        $this->cacheDir = $dir;

        return $this;
    }

    /**
     * Get directory for holding temporary files.
     *
     * @return  string
     */ 
    public function getTmpDir(): string
    {
        return $this->tmpDir;
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
        $dir = $tmpDir .  '/' . $this->getUri();
        if (!is_dir($dir)) {
            $this->buildDirFromUri($tmpDir);
        }

        $this->tmpDir = $dir;

        return $this;
    }

    /**
     * Get the URI of the original document.
     *
     * @return  string
     */ 
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Set the URI of the original document.
     *
     * @param  string  $uri  The URI of the original document.
     *
     * @return  self
     */ 
    public function setUri(string $uri): AbstractManager
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * Adds a cache file to the list of cache files
     *
     * @param string $fileName
     * 
     * @return string
     */
    public function addCacheFile(string $fileName): string
    {
        $this->cacheFiles[] = $this->getCacheDir() . '/' . $fileName;
    }

    /**
     * Get associative array for holding cache file paths.
     *
     * @return  array
     */ 
    public function getCacheFiles(): array
    {
        return $this->cacheFiles;
    }

    /**
     * Set associative array for holding cache file paths.
     *
     * @param  array  $cacheFiles  Associative array for holding cache file paths.
     *
     * @return  self
     */ 
    public function setCacheFiles(array $cacheFiles): AbstractManager
    {
        $this->cacheFiles = $cacheFiles;

        return $this;
    }

    /**
     * Adds a temporary file to the list of tmp files
     *
     * @param string $fileName
     * 
     * @return string
     */
    public function addTmpFile(string $fileName): string
    {
        $this->tmpFiles[] = $this->getTmpDir() . '/' . $fileName;
    }

    /**
     * Get associative array for holding temporary file paths.
     *
     * @return  array
     */ 
    public function getTmpFiles(): array
    {
        return $this->tmpFiles;
    }

    /**
     * Set associative array for holding temporary file paths.
     *
     * @param  array  $tmpFiles  Associative array for holding temporary file paths.
     *
     * @return  self
     */ 
    public function setTmpFiles(array $tmpFiles): AbstractManager
    {
        $this->tmpFiles = $tmpFiles;

        return $this;
    }

    /**
     * Get file Extension for cached/tmp files.
     *
     * @return  string
     */ 
    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    /**
     * Set file Extension for cached/tmp files.
     *
     * @param  string  $fileExtension  File Extension for cached files.
     *
     * @return  self
     */ 
    public function setFileExtension(string $fileExtension): AbstractManager
    {
        $this->fileExtension = $fileExtension;

        return $this;
    }
}
