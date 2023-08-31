<?php

namespace Dcol\Selectors;

interface SelectorInterface
{
    /**
     * Implementations of SelectorInterface must have a select() method that returns void.
     *
     * @return SelectorInterface
     */
    public function select(array $textList, string $pageUrl = null): SelectorInterface;

    /**
     * Return a list of selections
     * 
     * @return array
     */ 
    public function getSelections(): array;
}
