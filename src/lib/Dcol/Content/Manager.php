<?php

namespace Dcol\Content;

use Illuminate\Http\Client\Response;

use Dcol\AbstractManager,
    Dcol\Assistant\OpenAi\ChatCompletion,
    Dcol\Assistant\Request\OpenAiRequest;

class Manager extends AbstractManager
{
    const FILE_EXT='txt';

    const TYPE_TITLE = 'title';

    const TYPE_BLURB = 'blurb';

    const TYPE_PUBLICATION_DATE = 'publication_date';

    const TYPE_TWEET = 'tweet';

    const TYPE_AUTHORS = 'authors';

    const TYPE_TAGS = 'tags';

    const TYPE_WRITEUP = 'writeup';

    const TYPE_HTML_WRITEUP = 'html_writeup';

    const TYPE_SUMMARY = 'summary';

    const TYPE_RAW = 'raw';

    const TYPE_FOCUS_KEYPHRASE = 'focus_keyphrase';

    const TYPE_META_DESCRIPTION = 'meta_description';

    const PROMPT_TITLE = 'Don\'t use quotes, In less than 6 words write a title for the following:';

    const PROMPT_BLURB = 'Don\'t use quotes, write a 250 character summary of the following:';

    const PROMPT_PUBLICATION_DATE = 'For the following document, don\'t write anything but the date of publication, in this strict format YYYY-MM-DD:';

    const PROMPT_TWEET = 'Don\'t use quotes, create a tweet for the following, use less than 255 characters:';

    const PROMPT_AUTHORS = 'For the following document, make a list of only the documents\' authors\' names, list one name per author, in this format: name, name, name';

    const PROMPT_TAGS = 'Select terms as keywords that represent the most important parts of the following text, limit the number of terms to a total of 6, only answer in this format keyword 1, keyword 2, keyword 3, keyword 4, keyword 5, keyword 6:';

    const PROMPT_WRITEUP = 'summarize the following, speak in past tense, don\'t use phrases like "in the past", don\'t create any titles or headings:';

    const PROMPT_SUMMARY = 'Summarize the following, don\'t create any titles or headings:';

    const PROMPT_FOCUS_KEYPHRASE = 'Pick a sequence of four or five words from the following which encapsulates what it is about:';

    const PROMPT_META_DESCRIPTION = 'Reduce the following paragraph to use only 155 letters including spaces:';

    /**
     * OpenAI chatcompletion implementation.
     *
     * @var ChatCompletion
     */
    protected $chat;

    /**
     * Associative array for holding the content values.
     *
     * @var array
     */
    protected $content = [];

    /**
     * List of prompts by content type.
     *
     * @var array
     */
    protected $prompts = [
        Manager::TYPE_TITLE             => Manager::PROMPT_TITLE,
        Manager::TYPE_BLURB             => Manager::PROMPT_BLURB,
        Manager::TYPE_PUBLICATION_DATE  => Manager::PROMPT_PUBLICATION_DATE,
        Manager::TYPE_TWEET             => Manager::PROMPT_TWEET,
        Manager::TYPE_AUTHORS           => Manager::PROMPT_AUTHORS,
        Manager::TYPE_TAGS              => Manager::PROMPT_TAGS,
        Manager::TYPE_WRITEUP           => Manager::PROMPT_WRITEUP,
        Manager::TYPE_SUMMARY           => Manager::PROMPT_SUMMARY,
        Manager::TYPE_FOCUS_KEYPHRASE   => Manager::PROMPT_FOCUS_KEYPHRASE,
        Manager::TYPE_META_DESCRIPTION  => Manager::PROMPT_META_DESCRIPTION,
    ];

    /**
     * Constructor.
     *
     * @param ChatCompletion $chat
     * @param string $cacheDir
     * @param string $tmpDir
     * @param string $uri
     */
    public function __construct(ChatCompletion $chat, string $baseCacheDir, string $baseTmpDir, string $uri)
    {
        $this->setChat($chat);
        $this->setUri($uri);
        $this->setCacheDir($baseCacheDir);
        $this->setTmpDir($baseTmpDir);
        $this->setFileExtension(Manager::FILE_EXT);
    }

    /**
     * Create the content data.
     *
     * @param string $text
     * @return array
     */
    public function createContent(string $text): array
    {
        # If the text is empty bail out.
        if ($text == '') {
            throw new \Exception("Text for \"{$this->uri}\" was empty");
        }

        # Cache Raw
        $this->setCache($text, self::TYPE_RAW);

        # Truncated text to 2000 characters for use on some of the content type requests
        $truncatedText = $this->smartTruncate($text, 0, 10000);

        # writeup
        $this->content[self::TYPE_WRITEUP] = $this->createContentTypeMultipart($text, self::TYPE_WRITEUP);
        $this->setCache($this->content[self::TYPE_WRITEUP], self::TYPE_WRITEUP);
        # Truncate writeup for use in other requests
        $writeup = $this->smartTruncate($this->content[self::TYPE_WRITEUP], 0, 10000);

        # html_writeup
        $this->content[self::TYPE_HTML_WRITEUP] = $this->fiterHtml($this->content[self::TYPE_WRITEUP], self::TYPE_HTML_WRITEUP);
        $this->setCache($this->content[self::TYPE_HTML_WRITEUP], self::TYPE_HTML_WRITEUP);

        # publication_date
        $this->content[self::TYPE_PUBLICATION_DATE] = $this->filterPublicationDate($this->createContentType($truncatedText, self::TYPE_PUBLICATION_DATE));
        $this->setCache($this->content[self::TYPE_PUBLICATION_DATE], self::TYPE_PUBLICATION_DATE);

        # authors
        $authors = $this->createContentType($truncatedText, self::TYPE_AUTHORS);
        $this->setCache($authors, self::TYPE_AUTHORS);
        $this->content[self::TYPE_AUTHORS] = $this->filterAuthors(explode(',', $authors));

        # title
        $this->content[self::TYPE_TITLE] = $this->filterTitle($this->createContentType($writeup, self::TYPE_TITLE));
        $this->setCache($this->content[self::TYPE_TITLE], self::TYPE_TITLE);

        # blurb
        $this->content[self::TYPE_BLURB] = $this->createContentType($writeup, self::TYPE_BLURB);
        $this->setCache($this->content[self::TYPE_BLURB], self::TYPE_BLURB);

        # tweet
        $this->content[self::TYPE_TWEET] = $this->filterTweet($this->createContentType($writeup, self::TYPE_TWEET));
        $this->setCache($this->content[self::TYPE_TWEET], self::TYPE_TWEET);

        #tags
        $tags = $this->createContentType($writeup, self::TYPE_TAGS);
        $this->setCache($tags, self::TYPE_TAGS);
        $this->content[self::TYPE_TAGS] = $this->filterTags(explode(',', $tags));

        # summary
        $this->content[self::TYPE_SUMMARY] = $this->createContentType($writeup, self::TYPE_SUMMARY);
        $this->setCache($this->content[self::TYPE_SUMMARY], self::TYPE_SUMMARY);

        # meta_description
        $this->content[self::TYPE_META_DESCRIPTION] = $this->filterMetaDescription($this->createContentType($this->content[self::TYPE_WRITEUP], self::TYPE_META_DESCRIPTION));
        $this->setCache($this->content[self::TYPE_META_DESCRIPTION], self::TYPE_META_DESCRIPTION);

        # focus_keyphrase
        // $this->content[self::TYPE_FOCUS_KEYPHRASE] = $this->filterFocusKeyphrase($this->createContentType($this->content[self::TYPE_META_DESCRIPTION], self::TYPE_FOCUS_KEYPHRASE));
        // $this->setCache($this->content[self::TYPE_FOCUS_KEYPHRASE], self::TYPE_FOCUS_KEYPHRASE);
        // For SEO Purposes it seems better simply to use the Post title as the focus keyphrase
        $this->content[self::TYPE_FOCUS_KEYPHRASE] = strtolower($this->content[self::TYPE_TITLE]);

        return $this->content;
    }

    /**
     * Loops through long content in smaller chunks to append it to a larger output.
     *
     * @param string $text
     * @param string $type
     * @return string
     */
    public function createContentTypeMultipart(string $text, string $type): string
    {
        # If content is already cached just return the cache
        $cache = $this->getCache($type);
        if (false !== $cache) return $cache;

        # Set up the content collection string.
        $content = '';

        # Chunk Text into paragraphs
        $paragraphs = $this->getParagraphs($text);

        # Resume collecting tmp if it has already partially collected
        $tmpContent = $this->getTmp($type);
        if (false !== $tmpContent) {
            $content = $tmpContent;
            $paragraphs = $this->resumeContentMultipart($paragraphs, $content); 
        }
        
        # Loop through paragraphs and perform a content request for each paragraph
        foreach($paragraphs as $paragraph) {
            # Call createContentType for the paragraph with the multipart argument
            $content .= $this->createContentType($paragraph, $type, true);
        }

        $this->removeTmp($type);

        return $content;
    }

    /**
     * Makes a call to the OpenAI GPT API based on a text input and a content type.
     *
     * @param string $text
     * @param string $type
     * @param boolean $multipart
     * @return string
     */
    public function createContentType(string $text, string $type, bool $multipart = false): string
    {
        # If content is already cached just return the cache
        $cache = $this->getCache($type);
        if (false !== $cache) return $cache;

        if (false === $multipart) {
            # Remove the temporary file if it exists
            $this->removeTmp($type);
        }

        $response = $this->chat->generate($this->getRequest($text, $this->prompts[$type]));

        $content = $this->getContentFromResponse($response);

        if ($multipart) {
            $content .= "\n\n";
        }

        $this->setTmp($content, $type, $multipart);

        return $content;
    }

    /**
     * Simply truncating content can have bad interactions when working with chat completion
     * Instead of chopping off the content, in the middle of a word or sentance, remove
     * the last bit after a double line break.
     *
     * @param string $content
     * @param integer $offset
     * @param integer|null $length
     * @return string
     */
    public function smartTruncate(string $content, int $offset, int $length = null): string
    {
        $newContent = substr($content, $offset, $length);
        $lastParagraph = strrpos($newContent, "\n\n");
        return substr($newContent, 0, $lastParagraph);
    }

    /**
     * Modify content to be formatted as HTML
     *
     * @param string $text
     * @param string $type
     * @return string
     */
    protected function fiterHtml(string $text, string $type): string
    {
        # If content is already cached just return the cache
        $cache = $this->getCache($type);
        if (false !== $cache) return $cache;

        $this->removeTmp($type);

        $content = '<p>' . str_replace("\n\n", "</p>\n\n<p>", $text) . '</p>';

        $this->setTmp($content, $type);

        return $content;
    }

    /**
     * Checks a datetime string to determine if the date is valid
     *
     * @param string $date
     * @param string $format
     * @return boolean
     */
    protected function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Removes unwanted text from the meta description.
     *
     * @param string $content
     * @return string
     */
    protected function filterMetaDescription(string $content): string
    {
        # Reduce to 155 characters
        $content = substr(trim($this->filterDoubleQuotes($content)), 0, 155);

        # Remove periods
        $content = str_replace('.', '', $content);

        # If there is no period, truncate at the last space
        $lastSpace = strrpos($content, ' ');
        return substr($content, 0, $lastSpace);
    }

    /**
     * Removes unwanted text from the focus keyphrase.
     *
     * @param string $content
     * @return string
     */
    protected function filterFocusKeyphrase(string $content): string
    {
        # Remove commas
        $content = str_replace(',', '', $content);

        # Trim and remove quotes
        $content = trim($this->filterDoubleQuotes($content));

        # Reduce to only 5 words
        return preg_replace('/((\w+\W*){6}(\w+))(.*)/', '${1}', $content);
    }

    /**
     * Text filter for dates to return null if the datetime string is not valid
     *
     * @param string $content
     * @return string|null
     */
    protected function filterPublicationDate(string $content): string|null
    {
        return ($this->validateDate($content)) ? $content : null;
    }

    /**
     * Text filter for title to apply text fixes
     *
     * @param string $content
     * @return string
     */
    protected function filterTitle(string $content): string 
    {
        $content = str_replace('Titles: ', '', $content);
        $content = str_replace('Title: ', '', $content);
        $content = $this->filterDoubleQuotes($content);
        $content = trim($content);
        return substr($content, 0, 255);
    }

    /**
     * Text filter for tweet to apply text fixes
     *
     * @param string $content
     * @return string
     */
    protected function filterTweet(string $content): string 
    {
        # Remove "Tweet: " preamble from string
        $content = str_replace('Tweet: ', '', $content);

        # Remove non-ascii characters
        $content = preg_replace('/[^\x20-\x7E]/','', $content);

        # Remove Double Quotes, Trim and truncate to 255 characters
        return substr(trim($this->filterDoubleQuotes($content)), 0, 255);
    }

    /**
     * Filters the text in a list of tags to correct the text
     *
     * @param array $tags
     * @return array
     */
    protected function filterTags(array $tags): array
    {   
        $newTags = [];

        foreach($tags as $tag) {
            $newTags[] = $this->filterTag($tag);
        }

        return $newTags;
    }

    /**
     * Fixes any text problems with a tag
     *
     * @param string $tag
     * @return string
     */
    protected function filterTag(string $tag): string
    {
        if (strlen($tag) > 100) {
            $tag = substr($tag, 0, 50);
        }

        $tag = preg_replace('/keyword \d*\:/i', '', $tag);

        return trim($this->filterDoubleQuotes($tag));
    }

    /**
     * Filters the text in a list of authors to correct the text
     *
     * @param array $authors
     * @return array
     */
    protected function filterAuthors(array $authors): array
    {   
        $newAuthors = [];

        foreach($authors as $author) {
            $newAuthors[] = $this->filterAuthor($author);
        }

        return $newAuthors;
    }

    /**
     * Fixes any text problems with an author
     *
     * @param string $author
     * @return string
     */
    protected function filterAuthor(string $author): string
    {
        if (strlen($author) > 100) {
            $author = substr($author, 0, 100);
        }

        return trim($this->filterDoubleQuotes($author));
    }

    /**
     * Removes double quotes from string
     *
     * @param string $content
     * @return string
     */
    protected function filterDoubleQuotes(string $content): string {

        return str_replace('"', "", $content);
    }

    /**
     * Produces an OpenAiRequest object based on a prompt and text that the prompt refers to.
     *
     * @param string $text
     * @param string $prompt
     * @return OpenAiRequest
     */
    protected function getRequest(string $text, string $prompt): OpenAiRequest
    {
        #sanitize the text
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        return new OpenAiRequest([
            'messages' => [
                [
                    'role'      => 'system',
                    'content'   => 'You are a helpful assistant.'
                ],
                [
                    'role'      => 'user',
                    'content'   => "$prompt
                    
                    $text",
                ],
            ],
        ]);
    }

    /**
     * Takes a Response object with Data from an OpenAI chat request, and converts it into a string.
     *
     * @param Response $response
     * @return string
     */
    protected function getContentFromResponse(Response $response): string
    {
        $data = $response->json();
        if (isset($data['choices']) && isset($data['choices'][0]) && isset($data['choices'][0]['message'])) {
            $finishReason = $data['choices'][0]['finish_reason'];
            if ('stop' !== $finishReason) {
                throw new \Exception("Unusual termination from API: $finishReason");
            } else {
                return $data['choices'][0]['message']['content'];
            }
        } else {
            throw new \Exception("Unusable response from API\n\n" . print_r($data) );
        }
    }

    /**
     * Get openAI chatcompletion implementation.
     *
     * @return  ChatCompletion
     */ 
    public function getChat(): ChatCompletion
    {
        return $this->chat;
    }

    /**
     * Set openAI chatcompletion implementation.
     *
     * @param  ChatCompletion  $chat  OpenAI chatcompletion implementation.
     *
     * @return  self
     */ 
    public function setChat(ChatCompletion $chat): Manager
    {
        $this->chat = $chat;

        return $this;
    }


    /**
     * Get list of prompts by content type.
     *
     * @return  array
     */ 
    public function getPrompts()
    {
        return $this->prompts;
    }
}
