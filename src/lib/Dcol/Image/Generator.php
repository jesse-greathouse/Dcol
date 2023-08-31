<?php

namespace Dcol\Image;

use GDImage;

use App\Exceptions\ImageGeneratorException;

class Generator {

    const DEFAULT_WIDTH = '3840';

    const DEFAULT_HEIGHT = '2160';

    const DEFAULT_FONT = 'Roboto/Roboto-Black.ttf';

    const DEFAULT_FONT_SIZE = 120;

    /**
     * Width inn pixels
     *
     * @var int
     */
    protected $width;

    /**
     * Height inn pixels
     *
     * @var int
     */
    protected $height;

    /**
     * Name of the font file
     *
     * @var string
     */
    protected $font;

    /**
     * The size of the font in Pixels
     * 
     * @var int
     */
    protected $fontSize;

    /**
     * Directlroy where the fonts are stored.
     * 
     * @var string
     */
    protected $fontDir;

    /**
     * The file in which the image should be generated.
     *
     * @var string
     */
    protected $outputFile;

    public function __construct(string $font = null, string $fontSize = null, int $width = null, int $height = null, int $fontDir = null)
    {
        if (null !== $font) {
            $this->font = $font;
        }

        if (null !== $fontSize) {
            $this->fontSize = $fontSize;
        }

        if (null !== $width) {
            $this->width = $width;
        }

        if (null !== $height) {
            $this->height = $height;
        }

        if (null !== $fontDir) {
            $this->fontDir = $fontDir;
        }
    }

    /**
     * Creates an image from an existing background image.
     *
     * @param string $backgroundImage
     * @param string $text
     * @param string|null $outputFile
     * @return boolean
     */
    public function generateFromBackgroundImage(string $backgroundImage, string $text, string $outputFile = null): bool
    {
        $im = @imagecreatefrompng($backgroundImage);
        return $this->generate($text, $im, null, null, $outputFile);
    }

    /**
     * Creates an image from some minimal inputs.
     *
     * @param string|null $text
     * @param GDImage|null $im
     * @param int|null $width
     * @param int|null $height
     * @param string|null $outputFile
     * @return boolean
     */
    public function generate(string $text = null, GDImage $im = null, int $width = null, int $height = null, string $outputFile = null): bool
    {
        // Wordwrap text so hopefully more will fit on the image.
        $text = wordwrap($text, 40, "\n");

        if (null === $outputFile) {
            $outputFile = $this->getOutputFile();
        }

        // If image isn't created yet, assign the height and width.
        // If the image already exists, then just use the existing dimensions.
        if (null === $im) {
            if (null === $width) {
                $width = $this->getWidth();
            }

            if (null === $height) {
                $height === $this->getHeight();
            }
        } else {
            $width = imagesx($im);
            $height = imagesy($im);
        }

        if (null === $im) {
            $im  = @imagecreatetruecolor($width, $height);
            $black = imagecolorallocate($im, 0, 0, 0);
            // Fill background
            imagefilledrectangle($im, 0, 0, $width, $height, $black);
        }

        // imagecreatetruecolor returns false if there was a problem creating the image.
        if (false === $im) {
            throw new ImageGeneratorException("Unable to initialize the new GD image stream.");
        }

        // It's kind of pointless to do this without adding text.
        // But it's still possible.
        if (null !== $text) {

            // Font Settings
            $angle = '0';
            $font = $this->getFontDir() . '/' . $this->getFont();
            $fontSize = $this->getFontSize();
            $fontColor = imagecolorallocate($im, 255, 255, 255);

            // Calcations to center the text on the image.
            list($left, $bottom, $right, , , $top) = imageftbbox($fontSize, $angle, $font, $text);

            // Center Points of each coordinate.
            $centerX = $width / 2;
            $centerY = $height / 2;

            // Determine offset of text
            $lefOffset = ($right - $left) / 2;
            $topOffset = ($bottom - $top) / 2;

            // Text coordinates
            $x = $centerX - $lefOffset;
            $y = $centerY - $topOffset;

            // Add text to image
            imagettftext($im, $fontSize, $angle, $x, $y, $fontColor, $font, $text);
        }

        return imagepng($im, $outputFile, 9);
    }

    /**
     * Get width inn pixels
     *
     * @return  int
     */ 
    public function getWidth(): int
    {
        if (null === $this->width) {
            $this->width = self::DEFAULT_WIDTH;
        }
        return $this->width;
    }

    /**
     * Set width inn pixels
     *
     * @param  int  $width  Width inn pixels
     *
     * @return  self
     */ 
    public function setWidth(int $width): Generator
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Get height inn pixels
     *
     * @return  int
     */ 
    public function getHeight(): int
    {
        if (null === $this->height) {
            $this->height = self::DEFAULT_WIDTH;
        }
        return $this->height;
    }

    /**
     * Set height inn pixels
     *
     * @param  int  $height  Height inn pixels
     *
     * @return  self
     */ 
    public function setHeight(int $height): Generator
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Get name of the font file
     *
     * @return  string
     */ 
    public function getFont(): string
    {
        if (null === $this->font) {
            $this->font = self::DEFAULT_FONT;
        }

        return $this->font;
    }

    /**
     * Set name of the font file
     *
     * @param  string  $font  Name of the font file
     *
     * @return  self
     */ 
    public function setFont(string $font): Generator
    {
        $this->font = $font;

        return $this;
    }

    /**
     * Get directlroy where the fonts are stored.
     *
     * @return  string
     */ 
    public function getFontDir(): string
    {
        if (null === $this->fontDir) {
            $this->fontDir = storage_path('app/public/fonts');
        }

        return $this->fontDir;
    }

    /**
     * Set directlroy where the fonts are stored.
     *
     * @param  string  $fontDir  Directlroy where the fonts are stored.
     *
     * @return  self
     */ 
    public function setFontDir(string $fontDir): Generator
    {
        $this->fontDir = $fontDir;

        return $this;
    }

    /**
     * Get the size of the font in Pixels
     *
     * @return  int
     */ 
    public function getFontSize(): int
    {
        if (null === $this->fontSize) {
            $this->fontSize = self::DEFAULT_FONT_SIZE;
        }

        return $this->fontSize;
    }

    /**
     * Set the size of the font in Pixels
     *
     * @param  int  $fontSize  The size of the font in Pixels
     *
     * @return  self
     */ 
    public function setFontSize(int $fontSize): Generator
    {
        $this->fontSize = $fontSize;

        return $this;
    }

    /**
     * Get the file in which the image should be generated.
     *
     * @return  string
     */ 
    public function getOutputFile(): string
    {
        if (null === $this->fontSize) {
            $uniqueFilename = uniqid();
            $this->outputFile = storage_path('app/public/images') . "/$uniqueFilename.png";
        }
    
        return $this->outputFile;
    }

    /**
     * Set the file in which the image should be generated.
     *
     * @param  string  $outputFile  The file in which the image should be generated.
     *
     * @return  self
     */ 
    public function setOutputFile(string $outputFile): Generator
    {
        $this->outputFile = $outputFile;

        return $this;
    }
}
