<?php

namespace Dcol\Parsers;

abstract class Parser
{

    /**
     * The parser type designation.
     * 
     * @var string 
     */
    protected $type;

    /**
     * The path in which the file designated for this parser can be found.
     * 
     * @var string
     */
    protected $filePath;

    public function __construct(string $filePath) {
        $this->setFilePath($filePath);
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
     * Get the value of filePath
     *
     * @return  string;
     */ 
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Set the value of filePath
     *
     * @param  string;  $filePath
     *
     * @return  self
     */ 
    public function setFilePath(string $filePath)
    {
        $this->filePath = $filePath;

        return $this;
    }
}
