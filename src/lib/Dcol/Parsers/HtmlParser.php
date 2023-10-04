<?php

namespace Dcol\Parsers;

use PHPHtmlParser\Dom as Parser;

use Dcol\Parsers\ParserInterface;


class HtmlParser extends Parser implements ParserInterface
{
    CONST TYPE_NAME = 'html';
    CONST DEFAULT_SELECTOR = 'body';

    /**
     * https://github.com/paquettg/php-html-parser
     * 
     * @var Parser
     */
    protected $parser;

    /**
     * The CSS selector to isolate the part of the document to be parsed.
     * 
     * @var string 
     */
    protected $selector;

    public function __construct(string $filePath, string $selector = null)
    {
        parent::__construct($filePath);
        
        if (null === $selector) {
            $selector = self::DEFAULT_SELECTOR;
        }

        $this->selector = $selector;
        $this->parser = new Parser();
        $this->setType(self::TYPE_NAME);
    }

    public function parse(): string
    {   
        $this->parser->loadFromFile($this->getFilePath());
        $contents = $dom->find($this->getSelector());
        return $content->innerHtml;
    }

    /**
     * Get the value of the selector
     *
     * @return  string;
     */ 
    public function getSelector(): string
    {
        return $this->selector;
    }

    /**
     * Set the value of the selector
     *
     * @param  string  $selector
     *
     * @return  self
     */ 
    public function setSelector(string $selector): HtmlParser
    {
        $this->selector = $selector;

        return $this;
    }

}
