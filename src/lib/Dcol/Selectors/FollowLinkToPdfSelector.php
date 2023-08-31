<?php

namespace Dcol\Selectors;

use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

use App\Exception\UrlNotCompatibleException;

class FollowLinkToPdfSelector implements SelectorInterface
{

    const PATTERN = '/<a\s+(?:[^"\'>]+|"[^"]*"|\'[^\']*\')*href="([^"]+"|\'[^\']+\'|[^<>\s]+)"/i';

    protected array $selections = [];

    /**
     * Root uri of the website associated with the page
     * 
     * @var string
     */
    protected $baseUri;

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
    public function __construct(array $textList = null, $pageUrl = null)
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
        if (null !== $pageUrl) {
            $this->pageUrl = $pageUrl;
        }

        $output = [];
        foreach ($textList as $text) {
            preg_match_all(self::PATTERN, $text, $output);
        }
        
        if (isset($output[1])) {
            foreach($output[1] as $path) {
                if ($path === '/' || $path[0] === '#') continue;
                $uri = $this->getBaseUri() . $path;

                # Read the page and get the Body of the content.
                $response = Http::withRequestMiddleware(
                    function (RequestInterface $request) {
                        return $request->withHeader('X-Example', 'Value');
                    }
                )->get($uri);

                $body = $response->body();

                $s = new PdfFileSelector();

                foreach($s->select([$body], $this->pageUrl)->getSelections() as $selection) {
                    array_push($this->selections, $selection);
                }
            } 
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
     * Get beginning of the Url for the links
     *
     * @return  string
     */ 
    public function getBaseuri()
    {
        if (null === $this->baseUri) {
            if ($this->pageUrl === null) {
                $this->baseUri = $this->pageUrl;
                return $this->baseUri;
            }

            $parts = parse_url($this->pageUrl);

            if (!is_array($parts) || !isset($parts['host']) || NULL === $parts['host']) {
                throw new UrlNotCompatibleException('Url was not compatible or host is missing from url');
            }

            $domain = $parts['host'];
            $protocol = $parts['scheme'];

            $this->baseUri = sprintf('%s://%s', $protocol, $domain);
        }


        return $this->baseUri;
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
