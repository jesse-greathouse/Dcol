<?php

namespace Dcol\Parser;

use Dcol\Parse\BaseParser;

class PdfParser extends BaseParser
{
    CONST TYPE_NAME = 'pdf';

    public function __construct() {
        $this->setType( self::TYPE_NAME );
    }

}
