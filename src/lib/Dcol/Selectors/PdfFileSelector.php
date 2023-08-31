<?php

namespace Dcol\Selectors;

class PdfFileSelector implements SelectorInterface
{

    const PATTERN = "/[a-z_\–\-\/\.0-9\(\)\% ]+\.pdf/i";

    protected array $selections = [];

    /**
     * Url of the page from were the text came
     *
     * @var string
     */
    protected $pageUrl;

    /**
     * Undocumented function
     *
     * @param array|null $textList
     * @param string|null $pageUrl
     */
    public function __construct(array $textList = null, string $pageUrl = null)
    {
        if (null !== $pageUrl) {
            $this->pageUrl = $pageUrl;
        }
    
        if (null !== $textList) {
            $this->select($textList);
        }
    }

    /**
     * performs the selection from a list of strings
     * 
     * @param array $textList 
     * @param string|null $pageUrl
     * @return SelectorInterface
     */
    public function select(array $textList, string $pageUrl = null): SelectorInterface
    {
        $output = [];
        foreach ($textList as $text) {
            preg_match_all(self::PATTERN, $text, $output);
        }
        
        if (isset($output[0]) && 1 <= $output[0]) {
            array_push($this->selections, ...$output[0]); 
        }

        return $this;
    }

    /**
     * Get the value of selections
     * 
     * @return array
     */ 
    public function getSelections(): array
    {
        return array_unique($this->selections);
    }


    /**
     * Get url of the page from were the text came
     *
     * @return  string
     */ 
    public function getPageUrl()
    {
        return $this->pageUrl;
    }

    /**
     * Set url of the page from were the text came
     *
     * @param  string  $pageUrl  Url of the page from were the text came
     *
     * @return  self
     */ 
    public function setPageUrl(string $pageUrl)
    {
        $this->pageUrl = $pageUrl;

        return $this;
    }
}
