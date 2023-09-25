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
     * Returns the path to the cache file
     *
     * @param string $fileName
     * @return string
     */
    public function getCacheFile(string $fileName): string
    {
        return $this->getCacheDir() . '/' . $fileName . '.' . $this->getFileExtension();
    }

    /**
     * Returns content if content is cached. Returns false if cache doesn't exist.
     *
     * @param string $fileName
     * @return string|false
     */
    public function getCache(string $fileName): string|false
    {
        $file = $this->getCacheFile($fileName);
        if (file_exists($file)) {
            $content = $this->readFile($file);
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
     * @return void
     */
    public function setCache(string|null $content, string $fileName): void
    {
        if (null === $content) return;

        $file = $this->getCacheFile($fileName);
        try {
            $this->writeFile($content, $file);
        } catch(\Exception $e) {
            throw new \Exception("Unable to write to cahce file: \"$file\": " . $e->getMessage());
        }
    }

    /**
     * Removes a cache file
     *
     * @param string $fileName
     * @return void
     */
    public function removeCache(string $fileName): void
    {
        if (false !== $this->getCache($fileName)) {
            $file = $this->getCacheFile($fileName);
            unlink($file);
        }
    }

    /**
     * Returns the path to the cache file
     *
     * @param string $fileName
     * @return string
     */
    public function getTmpFile(string $fileName): string
    {
        return $this->getTmpDir() . '/' . $fileName . '.' . $this->getFileExtension();
    }

    /**
     * Returns content of temporary file. Returns false if file doesn't exist.
     *
     * @param string $fileName
     * @return string|false
     */
    public function getTmp(string $fileName): string|false
    {
        $file = $this->getTmpFile($fileName);
        if (file_exists($file)) {
            $content = $this->readFile($file);
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
    public function setTmp(string $content, string $fileName, bool $append = false): void
    {
        if (null === $content) return;

        $file = $this->getTmpFile($fileName);
        try {
            $this->writeFile($content, $file, $append);
        } catch(\Exception $e) {
            throw new \Exception("Unable to write to temporary file: \"$file\": " . $e->getMessage());
        }
    }

    /**
     * Removes a temporary file
     *
     * @param string $fileName
     * @return void
     */
    public function removeTmp(string $fileName): void
    {
        if (false !== $this->getTmp($fileName)) {
            $file = $this->getTmpFile($fileName);
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

    /**
     * Write to a file
     *
     * @param string $text
     * @param string $file
     * @param boolean $append
     * @return void
     */
    protected function writeFile(string $text, string $file, bool $append = false): void 
    {
        $mode = $append ? 'a' : 'w';
        $fp = fopen($file, $mode);
        fwrite($fp, $text);
        fclose($fp);
    }

    /**
     * Read to a File
     *
     * @param string $file
     * @return string
     */
    protected function readFile(string $file): string 
    {
        $fp = fopen($file, 'r');
        $text = fread($fp, filesize($file));
        fclose($fp);
        return $text;
    }
}
