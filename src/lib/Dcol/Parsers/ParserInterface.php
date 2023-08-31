<?php

namespace Dcol\Parsers;

interface ParserInterface
{
    /**
     * Implementations of ParserInterface must have a parse() method that returns a string.
     *
     * @return string
     */
    public function parse(): string;
}
