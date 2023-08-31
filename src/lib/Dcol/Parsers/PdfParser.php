<?php

namespace Dcol\Parsers;

use Smalot\PdfParser\Parser as SmalotParser;
use Smalot\PdfParser\Document as SmalotDocument;

use Dcol\Parsers\ParserInterface;


class PdfParser extends Parser implements ParserInterface
{
    CONST TYPE_NAME = 'pdf';

    /**
     * 
     * 
     * @var Smalot\PdfParser\Parser
     */
    protected $parser;

    public function __construct(string $filePath)
    {
        parent::__construct($filePath);
        $this->parser = new SmalotParser();
        $this->setType(self::TYPE_NAME);
    }

    public function parse(): string
    {
        $pdf = $this->parser->parseFile($this->filePath);
        return $pdf->getText();
    }

}
