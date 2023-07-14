<?php

namespace Dcol\Parser;

abstract class BaseParser
{
    /**
     * 
     * 
     * @var string
     */
    protected $name;

    /**
     * 
     * 
     * @var string 
     */
    protected $type;

    /**
     * 
     * 
     * @var string
     */
    protected $pages;

    /**
     * 
     * 
     * @var string
     */
    protected $header;

    /**
     * 
     * 
     * @var string
     */
    protected $body;

    /**
     * Get the value of name
     *
     * @return  string
     */ 
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @param  string  $name
     *
     * @return  self
     */ 
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the value of type
     *
     * @return  string
     */ 
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the value of type
     *
     * @param  string  $type
     *
     * @return  self
     */ 
    public function setType(string $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the value of pages
     *
     * @return  string
     */ 
    public function getPages()
    {
        return $this->pages;
    }

    /**
     * Set the value of pages
     *
     * @param  string  $pages
     *
     * @return  self
     */ 
    public function setPages(string $pages)
    {
        $this->pages = $pages;

        return $this;
    }

    /**
     * Get the value of body
     *
     * @return  string
     */ 
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set the value of body
     *
     * @param  string  $body
     *
     * @return  self
     */ 
    public function setBody(string $body)
    {
        $this->body = $body;

        return $this;
    }
}
