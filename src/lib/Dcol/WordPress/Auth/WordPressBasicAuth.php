<?php

namespace Dcol\WordPress\Auth;

use Dcol\WordPress\Request\WordPressRequestInterface;

class WordPressBasicAuth implements WordPressAuthInterface
{
    const HEADER_KEY = 'Authorization';

    /**
     * WordPress API user's username
     *
     * @var string
     */
    protected $username;

    /**
     * WordPress API user's password
     *
     * @var string
     */
    protected $password;

    /**
     * Simple list of authentication arg names.
     *
     * @var array
     */
    protected static $argNames = [
        'username',
        'password',
    ];

    /**
     * Constructor allows shortcutting the username and password input on instantiation
     *
     * @param string|null $header
     * @param string|null $body
     */
    public function __construct(string $username = null, string $password = null) {
        if (null !== $username) {
            $this->setUsername($username);
        }

        if (null !== $password) {
            $this->setPassword($password);
        }
    }

    /**
     * Adds the Authorization to the request
     *
     * @param WordPressRequestInterface $request
     * @return WordPressRequestInterface
     */
    public function addAuth(WordPressRequestInterface $request): WordPressRequestInterface
    {
        $value = 'Basic ' . base64_encode($this->getUsername() . ':' . $this->getPassword());
        return $request->addHeader(self::HEADER_KEY, $value);
    }


    /**
     * Returns a simple list of authentication argument names
     *
     * @return array
     */
    public static function getArgNames(): array
    {
        return static::$argNames;
    }

    /**
     * Get wordPress API user's username
     *
     * @return  string
     */ 
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Sets the GPT authorization secret for the request
     *
     * @param string $authKey
     * @return WordPressBasicAuth
     */
    public function setUsername(string $username): WordPressBasicAuth
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get wordPress API user's password
     *
     * @return  string
     */ 
    public function getPassword(): string
    {
        return $this->password;
    }


    /**
     * Set wordPress API user's password
     *
     * @param  string  $password  WordPress API user's password
     *
     * @return  self
     */ 
    public function setPassword(string $password): WordPressBasicAuth
    {
        $this->password = $password;

        return $this;
    }
}
