<?php

namespace Dcol\Image;

use Illuminate\Http\Client\Response;

use App\Models\Document,
    App\Models\BlogPostDefault,
    App\Exceptions\ImageGeneratorException;

use Dcol\AbstractManager;

class Manager extends AbstractManager {

    const FILE_EXT='png';

    /**
     * Image generator class.
     *
     * @var Generator
     */
    protected $generator;

    /**
     * Document model instance.
     *
     * @var Document
     */
    protected $document;

    /**
     * Constructor.
     *
     * @param Generator $generator
     * @param Document $generator
     * @param string $baseCacheDir
     * @param string $baseTmpDir
     * @param string $uri
     */
    public function __construct(Generator $generator, Document $document, string $baseCacheDir, string $baseTmpDir, string $uri)
    {
        $this->setGenerator($generator);
        $this->setDocument($document);
        $this->setUri($uri);
        $this->setCacheDir($baseCacheDir);
        $this->setTmpDir($baseTmpDir);
        $this->setFileExtension(Manager::FILE_EXT);
    }

    /**
     * Creates a featured image for the document and returns the full path to the image.
     *
     * @param string|null $backgroundImage
     * @return string
     */
    public function createFeaturedImage(string $backgroundImage = null): string
    {
        $document = $this->getDocument();
        $generator = $this->getGenerator();
        $imageFileName = $this->makeFileName(self::FILE_EXT);
        $outputFile = $this->getCacheDir() . '/' . $imageFileName . '.' . $this->getFileExtension();

        if (file_exists($outputFile)) {
            return $outputFile;
        }

        if (null !== $backgroundImage) {
            $isGenerated = $generator->generateFromBackgroundImage($backgroundImage, $document->content->title, $outputFile);
        } else {
            $isGenerated = $generator->generate($document->title, null, null, null, $outputFile);
        }

        if (false === $isGenerated) {
            throw new ImageGeneratorException("\"$imageFileName\" could not be created.");
        }

        return $outputFile;
    }

    /**
     * Convert's the document's content title into a slug.
     *
     * @param string $preface any string to prepend to the slug
     * @return string
     */
    public function makeFileName($prepend = ''): string
    {
        $title = $this->getDocument()->content->title;
        if ('' !== $prepend) {
            $title = "$prepend $title";
        }
        $title = preg_replace("/[^A-Za-z0-9 ]/", '', $title);
        $title = strtolower($title);
        $title = str_replace(' ', '-', $title);
        # Truncate the file name at 128 characters.
        $title = substr($title, 0, 128);
        return $title;
    }

    /**
     * Get image generator instance.
     *
     * @return  Generator 
     */ 
    public function getGenerator(): Generator
    {
        return $this->generator;
    }

    /**
     * Set image generator instance.
     *
     * @param  Generator  $generator image generator instance.
     *
     * @return  self
     */ 
    public function setGenerator(Generator $generator): Manager
    {
        $this->generator = $generator;

        return $this;
    }

    /**
     * Get document model instance.
     *
     * @return  Document
     */ 
    public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * Set document model instance.
     *
     * @param  Document  $document  Document model instance.
     *
     * @return  self
     */ 
    public function setDocument(Document $document): Manager
    {
        $this->document = $document;

        return $this;
    }
}
