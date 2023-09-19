<?php 

namespace App\Console\Commands;

/**
 * Trait that extends core functionality to include a prepended string to console output, if the method exists.
 */
trait PrependsOutput
{
    public function line($string, $style = null, $verbosity = null)
    {
        parent::line($this->prepend($string), $style, $verbosity);
    }

    public function comment($string, $style = null, $verbosity = null)
    {
        parent::comment($this->prepend($string), $style, $verbosity);
    }

    public function warn($string, $style = null, $verbosity = null)
    {
        parent::warn($this->prepend($string), $style, $verbosity);
    }

    protected function prepend($string)
    {
        if (method_exists($this, 'getPrependString')) {
            return $this->getPrependString($string).$string;
        }

        return $string;
    }
}
