<?php 

namespace App\Console\Commands;

/**
 * Trait that adds output functionality.
 */
trait OutputCheck
{
    protected function isVerbose()
    {
        return $this->getOutput()->isVerbose();
    }
}
