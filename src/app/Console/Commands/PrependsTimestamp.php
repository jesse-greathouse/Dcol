<?php

namespace App\Console\Commands;

/**
 * Trait that uses the prepend output design to prepend a timestamp string to console output.
 */
trait PrependsTimestamp
{
    protected function getPrependString($string)
    {
        return date(property_exists($this, 'outputTimestampFormat') ?
            $this->outputTimestampFormat : '[Y-m-d H:i:s]').' ';
    }
}
