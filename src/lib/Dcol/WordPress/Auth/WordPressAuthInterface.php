<?php

namespace Dcol\WordPress\Auth;

use Dcol\WordPress\Request\WordPressRequestInterface;

interface WordPressAuthInterface
{
    /**
     * Must have an addAuth() method that handles adding authorization qualities.
     *
     * @return WordPressRequestInterface
     */
    public function addAuth(WordPressRequestInterface $request): WordPressRequestInterface;

    /**
     * Must have a getArgNames() method that returns a simple list of named authentication arg parameters.
     *
     * @return array
     */
    public static function getArgNames(): array;
}
