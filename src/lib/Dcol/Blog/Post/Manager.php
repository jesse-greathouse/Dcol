<?php

namespace Dcol\Blog\Post;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Blade;

use App\Models\Blog,
    App\Models\BlogMedia,
    App\Models\BlogPost,
    App\Models\BlogPostDefault,
    App\Models\Document,
    App\Models\Page,
    App\Models\Site,
    App\Exceptions\MissingDefaultBlogPostValuesException,
    Dcol\AbstractManager,
    Dcol\WordPress\Posts,
    Dcol\WordPress\Media,
    Dcol\WordPress\Tags,
    Dcol\WordPress\Auth\WordPressAuthInterface,
    Dcol\WordPress\Request\WordPressRequest;

class Manager extends AbstractManager
{
    const FILE_EXT='json';

    const POST_STATUS_PUBLISH = 'publish';

    const POST_STATUS_DRAFT = 'draft';

    /**
     * Instance of a Blog model.
     *
     * @var Blog
     */
    protected $blog;

    /**
     * Instance of a Blog model.
     *
     * @var Document
     */
    protected $document;

    /**
     * Instance of a Page model.
     *
     * @var Page
     */
    protected $page;

    /**
     * Instance of a Site model.
     *
     * @var Site
     */
    protected $site;

    /**
     * Instance of a BlogPostDefaults model.
     *
     * @var BlogPostDefaults
     */
    protected $blogPostDefaults;
    
    /**
     * WordPressAuthInterface instance for this blog.
     *
     * @var WordPressAuthInterface
     */
    protected $auth;

    /**
     * WordPress API Posts implementation.
     *
     * @var Posts
     */
    protected $postsApi;

    /**
     * WordPress API Media implementation.
     *
     * @var Media
     */
    protected $mediaApi;

    /**
     * WordPress API Tag implementation
     *
     * @var Tags
     */
    protected $tagsApi;

    /**
     * The path where documents are cached
     *
     * @var string
     */
    protected $documentsCacheDir;

    /**
     * Constructor.
     *
     * @param Blog $blog
     * @param Document $document
     * @param string $baseCacheDir
     * @param string $baseTmpDir
     * @param string $uri
     */
    public function __construct(Blog $blog, Document $document, string $baseCacheDir, string $baseTmpDir, string $uri)
    {
        $this->setBlog($blog);
        $this->setDocument($document);
        $this->setCacheDir($baseCacheDir);
        $this->setUri($uri);
        $this->setDocumentsCacheDir($baseCacheDir);
        $this->setTmpDir($baseTmpDir);
        $this->setFileExtension(Manager::FILE_EXT);
        $this->setPostsApi(new Posts($this->getAuth(), $this->getBlog()->domain_name));
        $this->setMediaApi(new Media($this->getAuth(), $this->getBlog()->domain_name));
        $this->setTagsApi(new Tags($this->getAuth(), $this->getBlog()->domain_name));
    }

    /**
     * Creates a post for the document on the specified blog.
     *
     * @param BlogMedia|null $blogMediaDocument
     * @param BlogMedia|null $blogMediaFeaturedImage
     * @param boolean $publish
     * @return BlogPost
     */
    public function makePost(BlogMedia $blogMediaDocument = null, BlogMedia $blogMediaFeaturedImage = null, $publish = false): BlogPost
    {
        $headers = ['Content-Type' => 'application/json'];
        $content = $this->getDocument()->content;
        $status = ($publish) ? self::POST_STATUS_PUBLISH : self::POST_STATUS_DRAFT;

        $request = new WordPressRequest([
            'slug'              => $this->makeSlug(),
            'status'            => $status,
            'title'             => $content->title,
            'author'            => $this->getBlogPostDefaults()->author,
            'content'           => $this->makeContent($blogMediaDocument),
            'excerpt'           => $content->meta_description,
            'alt_text'          => $content->title,
            'caption'           => $content->focus_keyphrase,
            'description'       => $content->meta_description,
            'featured_media'    => $blogMediaFeaturedImage->media_id,
            'categories'        => [ $this->getBlogPostDefaults()->category ],
            'tags'              => $this->getTagList(),
            'yoast_meta'        => [
                'yoast_wpseo_focuskw'   => strtolower($content->title),
                'yoast_wpseo_metadesc'  => $content->meta_description,
            ],
        ], $headers);

        $response = $this->getPostsApi()->post($request);
        $blogPost = $this->getBlogPostFromResponse($response);

        # Since this is a new BlogPost, set the is_tweeted property to false
        $blogPost->is_tweeted = false;

        return $blogPost;
    }

    /**
     * Synchronizes a BlogPost record with the updated post on the blog.
     *
     * @param BlogPost $blogPost
     * @return BlogPost
     */
    public function syncBlogPost(BlogPost $blogPost): BlogPost
    {
        $request = new WordPressRequest(null, ['Content-Type' => 'application/json']);
        $response = $this->getPostsApi()->get($request, $blogPost->post_id);
        $updatedBlogPost = $this->getBlogPostFromResponse($response);

        $props = [
            'post_id',
            'slug',
            'url',
            'author',
            'publication_date',
            'type',
            'content',
            'featured_media',
            'title',
            'category',
            'is_published',
            'blog_id',
            'document_id',
            'focus_keyphrase',
            'meta_description',
            'seo_score',
        ];

        foreach($props as $prop) {
            $blogPost->{$prop} = $updatedBlogPost->{$prop};
        }

        return $blogPost;
    }

    /**
     * Uses the Tags Api to create a new tag based on a value
     *
     * @param string $value
     * @return integer
     */
    public function makeTag(string $value): int
    {
        $headers = ['Content-Type' => 'application/json'];

        $request = new WordPressRequest([
            'name'  => $value,
            'slug'  => $this->sluggify($value),
        ], $headers);

        $response = $this->getTagsApi()->post($request);
        $data = $response->json();
        return $data['id'];
    }

    /**
     * Uploads a new document to the blog
     *
     * @return BlogMedia
     */
    public function uploadDocument(): BlogMedia
    {
        $file = $this->getDocumentsCacheDir() .  '/' . $this->getUri();
        return $this->uploadFile($file);
    }

    /**
     * Uploads a new image to the blog
     *
     * @param string $file
     * @param string $contentType
     * @return BlogMedia
     */
    public function uploadFile(string $file): BlogMedia
    {
        # Upload the media
        $fileName = basename($file);
        $request = new WordPressRequest(null, [
            'Content-Type'          => 'multipart/form-data',
            'Content-Disposition'   => "attachment; filename=\"$fileName\"",
        ]);
        $response = $this->getMediaApi()->setAttachment($file)->post($request);
        $data = $response->json();
        
        # Update the media with the relevant info.
        $request = $this->getEditMediaRequest($data['id'], $fileName);
        $response = $this->getMediaApi()->post($request, $data['id']);
        return $this->getBlogMediaFromResponse($response);
    }

    /**
     * Returns an BlogMedia instance
     *
     * @param Response $response
     * @return array
     */
    protected function getBlogMediaFromResponse(Response $response): BlogMedia
    {
        $data = $response->json();

        return new BlogMedia([
            'media_id'          => $data['id'],
            'slug'              => $data['slug'],
            'url'               => $data['link'],
            'source_url'        => $data['source_url'],
            'author'            => $data['author'],
            'publication_date'  => $data['date'],
            'type'              => $data['type'],
            'media_type'        => $data['media_type'],
            'mime_type'         => $data['mime_type'],
            'title'             => $data['title']['raw'],
            'caption'           => $data['caption']['raw'],
            'description'       => $data['description']['raw'],
            'media_details'     => $data['media_details']['filesize'],
            'is_published'      => true,
            'blog_id'           => $this->getBlog()->id,
            'document_id'       => $this->getDocument()->id,
        ]);
    }

    /**
     * Returns an BlogPost instance
     *
     * @param Response $response
     * @return array
     */
    protected function getBlogPostFromResponse(Response $response): BlogPost
    {
        $data = $response->json();
        $isPublished = ($data['status'] === self::POST_STATUS_PUBLISH) ? true : false;
        $isDraft = ($data['status'] === self::POST_STATUS_DRAFT) ? true : false;
        $focusKeyphrase = null;
        $metaDescription = null;
        $seoScore = null;

        // Figure out url
        if (isset($data['permalink_template'])) {
            $url = str_replace('%postname%', $data['slug'], $data['permalink_template']);
        } else {
            $url = $data['link'];
        }

        // Figure out title
        if (isset($data['title']['raw'])) {
            $title = $data['title']['raw'];
        } else {
            $title = $data['title']['rendered'];
        }

        if (isset($data['content']['raw'])) {
            $content = $data['content']['raw'];
        } else {
            $content = $data['content']['rendered'];
        }

        // focus_keyword can be enabled from the dcol-wordpress-addon
        if (isset($data['focus_keyword'])) {
            $focusKeyphrase = $data['focus_keyword'];
        }

        // meta_descrption can be enabled from the dcol wordpress addon
        if (isset($data['meta_description'])) {
            $metaDescription = $data['meta_description'];
        }

        // seo_score can be enabled from the dcol wordpress addon
        if (isset($data['seo_score']) && is_numeric($data['seo_score'])) {
            $seoScore = (int) $data['seo_score'];
        }

        return new BlogPost([
            'post_id'           => $data['id'],
            'slug'              => $data['slug'],
            'url'               => $url,
            'focus_keyphrase'   => $focusKeyphrase,
            'meta_description'  => $metaDescription,
            'seo_score'         => $seoScore,
            'author'            => $data['author'],
            'publication_date'  => $data['date'],
            'type'              => $data['type'],
            'content'           => $content,
            'featured_media'    => $data['featured_media'],
            'title'             => $title,
            'category'          => $data['categories'][0],
            'is_published'      => $isPublished,
            'blog_id'           => $this->getBlog()->id,
            'document_id'       => $this->getDocument()->id,
        ]);
    }

    /**
     * Formulates a WordPress Request for a Media Upload
     *
     * @param int $id
     * @param string|null $fileName
     * @return WordPressRequest
     */
    public function getEditMediaRequest(int $id, string $fileName = null): WordPressRequest
    {
        $headers = ['Content-Type' => 'application/json'];
        $content = $this->getDocument()->content;
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        
        return new WordPressRequest([
            'id'            => $id,
            'slug'          => $this->makeSlug($ext),
            'title'         => $content->title,
            'author'        => $this->getBlogPostDefaults()->author,
            'alt_text'      => $content->title,
            'caption'       => $content->focus_keyphrase,
            'description'   => $content->meta_description,
        ], $headers);
    }

    /**
     * Returns an id of a Tag if it exists, if not returns false
     *
     * @param string $tagValue
     * @return integer|false
     */
    public function getTagIdByValue(string $tagValue): int|false
    {
        $cacheKey = Tags::ENDPOINT . '/' . $tagValue;
        $body = $this->getCache($cacheKey);
        
        # If it's not cached, call the API
        if (false === $body) {
            $headers = ['Content-Type' => 'application/json'];
            $request = new WordPressRequest(null, $headers);
            $response = $this->getTagsApi()->getByValue($request, $this->sluggify($tagValue));
            $body = $response->body();
        }
        
        $data = json_decode($body, true);

        if (!isset($data[0]) && !isset($data[0]['id'])) {
            return false;
        } else {
            $this->setCache($body, $cacheKey);
            return $data[0]['id'];
        }
    }

    /**
     * Downloads the featured_media image of the blog according the the BlogPostDefaults
     *
     * @return string
     */
    public function downloadDefaultFeaturedMediaImage(): string
    {
        
        $id = $this->getBlogPostDefaults()->featured_media;
        $cacheKey = Media::ENDPOINT . '/' . $id;

        # If the object is already cached, just use the cached response.
        $body = $this->getCache($cacheKey);

        # If it's not cached, call the API
        if (false === $body) {
            $request = new WordPressRequest(null, ['Content-Type' => 'application/json']);
            $response = $this->getMediaApi()->get($request, $id);
            $body = $response->body();
            $this->setCache($body, $cacheKey);
        }

        $data = json_decode($body, true);
        $imageUrl = $data['link'];
        $fileName = basename($data['media_details']['file']);
        $imageFile = $this->getCacheDir() . '/' . $fileName;

        if (!file_exists($imageFile)) {
            file_put_contents($imageFile, file_get_contents($imageUrl));
        }

        return $imageFile;
    }

    /**
     * Produces the content of a blog post with a saved template.
     *
     * @param BlogMedia $blogMediaDocument
     * @return string
     */
    public function makeContent(BlogMedia $blogMediaDocument): string
    {
        $defaults = $this->getBlogPostDefaults();
        $content = $this->getDocument()->content;
        return Blade::render($defaults->template, 
            [
                'class_1'           => $defaults->class_1,
                'class_2'           => $defaults->class_2,
                'class_3'           => $defaults->class_3,
                'document_url'      => $blogMediaDocument->source_url,
                'file_name'         => basename($blogMediaDocument->source_url),
                'blurb'             => $content->blurb,
                'focus_keyphrase'   => ucwords($content->focus_keyphrase),
                'html_writeup'      => $content->html_writeup,
            ]
        );
    }

    /**
     * Convert's the document's content title into a slug.
     *
     * @param string $preface any string to prepend to the slug
     * @return string
     */
    public function makeSlug($prepend = ''): string
    {
        $title = $this->getDocument()->content->title;
        if ('' !== $prepend) {
            $title = "$prepend $title";
        }
    
        return $this->sluggify($title);
    }

    /**
     * Convert's a string into a slug.
     *
     * @param string $string any string to prepend to the slug
     * @return string
     */
    public function sluggify($string): string
    {
        $string = preg_replace("/[^A-Za-z0-9 ]/", '', $string);
        $string = strtolower($string);
        $string = str_replace(' ', '-', $string);
        return $string;
    }

    /**
     * Returns a flat list of tags from the content
     *
     * @return array
     */
    public function getTagList(): array
    {
        $list = [];

        foreach($this->getDocument()->tags as $tag) {
            $id = $this->getTagIdByValue($tag->value);
            if (false === $id) {
                $id = $this->makeTag($tag->value);
            }

            $list[] = $id;
        }

        return $list;
    }

    /**
     * Removes an arbitrary list of small words from a sentence.
     *
     * @param string $sentence
     * @return string
     */
    protected function removeSmallLastWords(string $sentence): string
    {
        # Remove a list of small words from the end of the string.
        $smallWords = [
            'and',
            'in',
            'the',
            'for',
            'to',
            'be',
            'if',
            'a',
            'from',
            'at',
            'but',
            'or',
            'of',
            'thus',
            'before',
        ];

        $parts = explode(' ', $sentence);
        $reverse = array_reverse($parts);

        foreach($reverse as $i => $word) {
            if (in_array($word, $smallWords)) {
                unset($reverse[$i]);
            } else {
                break;
            }
        }

        $result = implode(' ', array_reverse($reverse));

        return $result;
    }

    /**
     * Converts an api uri path to a file name for caching purposes
     *
     * @param string $fileName
     * @return string
     */
    protected function uriToFileName(string $fileName): string
    {
        return str_replace('/', '-', trim($fileName, '/'));
    }

    /**
     * Returns content if content is cached. Returns false if cache doesn't exist.
     *
     * @param string $fileName
     * @return string|false
     */
    public function getCache(string $fileName): string|false
    {
        return parent::getCache($this->uriToFileName($fileName));
    }

    /**
     * Writes the content to the given file cache by type
     *
     * @param string $content|null
     * @param string $fileName
     * @return string|false
     */
    public function setCache(string|null $content, string $fileName): void
    {
        parent::setCache($content, $this->uriToFileName($fileName));
    }

    /**
     * Removes a cache file
     *
     * @param string $fileName
     * @return void
     */
    public function removeCache(string $fileName): void
    {
        if (false !== $this->getCache($fileName)) {
            $file = $this->getCacheDir() . '/' . $fileName . '.' . $this->getFileExtension();
            unlink($file);
        }

        parent::removeCache($this->uriToFileName($fileName));
    }

    /**
     * Returns content of temporary file. Returns false if file doesn't exist.
     *
     * @param string $fileName
     * @return string|false
     */
    public function getTmp(string $fileName): string|false
    {
        return parent::getTmp($this->uriToFileName($fileName));
    }

    /**
     * Writes the content to the given temporary file by type
     *
     * @param string $content
     * @param string $fileName
     * @param boolean $append
     * @return string|false
     */
    public function setTmp(string $content, string $fileName, $append = false): void
    {
        parent::setTmp($content, $this->uriToFileName($fileName));
    }

    /**
     * Removes a temporary file
     *
     * @param string $fileName
     * @return void
     */
    public function removeTmp(string $fileName): void
    {
        parent::removeTmp($this->uriToFileName($fileName));
    }

    /**
     * Get wordPress API Posts implementation.
     *
     * @return  Posts
     */ 
    public function getPostsApi(): Posts
    {
        return $this->postsApi;
    }

    /**
     * Set wordPress API Posts implementation.
     *
     * @param  Posts  $postsApi  WordPress API Posts implementation.
     *
     * @return  self
     */ 
    public function setPostsApi(Posts $postsApi): Manager
    {
        $this->postsApi = $postsApi;

        return $this;
    }

    /**
     * Get wordPress API Media implementation.
     *
     * @return  Media
     */ 
    public function getMediaApi(): Media
    {
        return $this->mediaApi;
    }

    /**
     * Set wordPress API Media implementation.
     *
     * @param  Media  $mediaApi  WordPress API Media implementation.
     *
     * @return  self
     */ 
    public function setMediaApi(Media $mediaApi): Manager
    {
        $this->mediaApi = $mediaApi;

        return $this;
    }

    /**
     * Get wordPress API Tag implementation
     *
     * @return  Tags
     */ 
    public function getTagsApi(): Tags
    {
        return $this->tagsApi;
    }

    /**
     * Set wordPress API Tag implementation
     *
     * @param  Tags  $tagApi  WordPress API Tag implementation
     *
     * @return  self
     */ 
    public function setTagsApi(Tags $tagsApi): Manager
    {
        $this->tagsApi = $tagsApi;

        return $this;
    }

    /**
     * Get instance of a Blog model
     *
     * @return  Blog
     */ 
    public function getBlog(): Blog
    {
        return $this->blog;
    }

    /**
     * Set instance of a Blog model
     *
     * @param  Blog  $blog  Instance of a Blog model
     *
     * @return  self
     */ 
    public function setBlog(Blog $blog): Manager
    {
        $this->blog = $blog;

        return $this;
    }

    /**
     * Get WordPressAuthInterface instance for this blog
     *
     * @return  WordPressAuthInterface
     */ 
    public function getAuth(): WordPressAuthInterface
    {   
        if (null === $this->auth) {
            $args = [];
            $blog = $this->getBlog();

            # Dynamically create the args for the auth class.
            foreach($blog->blogAuth->class::getArgNames() as $name) {
                $args[] = $blog->{$name};
            }
            
            $r = new \ReflectionClass($blog->blogAuth->class);
            $this->auth =  $r->newInstanceArgs($args);
        }

        return $this->auth;
    }

    /**
     * Set WordPressAuthInterface instance for this blog
     *
     * @param  WordPressAuthInterface $auth instance for this blog
     *
     * @return  self
     */ 
    public function setAuth(WordPressAuthInterface $auth): Manager
    {
        $this->auth = $auth;

        return $this;
    }

    /**
     * Get instance of a BlogPostDefaults model.
     *
     * @return  BlogPostDefault
     */ 
    public function getBlogPostDefaults(): BlogPostDefault
    {
        if (null === $this->blogPostDefaults) {
            $default = BlogPostDefault::where('blog_id', $this->getBlog()->id)
                ->where('site_id', $this->getSite()->id)
                ->first();

            if (null === $default) {
                throw new MissingDefaultBlogPostValuesException(
                    sprintf(
                        "Missing default blog post values for blog: %s and site: %s",
                        $this->getBlog()->domain_name,
                        $this->getSite()->domain_name
                    )
                );
            }

            $this->blogPostDefaults = $default;
        } 
        return $this->blogPostDefaults;
    }

    /**
     * Set instance of a BlogPostDefaults model.
     *
     * @param  BlogPostDefaults  $blogPostDefaults  Instance of a BlogPostDefaults model.
     *
     * @return  self
     */ 
    public function setBlogPostDefaults(BlogPostDefaults $blogPostDefaults): Manager
    {
        $this->blogPostDefaults = $blogPostDefaults;

        return $this;
    }

    /**
     * Get instance of a Blog model.
     *
     * @return  Document
     */ 
    public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * Set instance of a Blog model.
     *
     * @param  Document  $document  Instance of a Blog model.
     *
     * @return  self
     */ 
    public function setDocument(Document $document): Manager
    {
        $this->document = $document;

        return $this;
    }

    /**
     * Get the URI of the original document.
     * Overloads abstract getUri
     *
     * @return  string
     */ 
    public function getUri(): string
    {
        if (null === $this->uri) {
            $this->uri = $this->getDocument()->uri;
        }

        return $this->uri;
    }

    /**
     * Get instance of a Page model.
     *
     * @return  Page
     */ 
    public function getPage(): Page
    {        
        if (null === $this->page) {
            $this->page = $this->getDocument()->page;
        }

        return $this->page;
    }

    /**
     * Set instance of a Page model.
     *
     * @param  Page  $page  Instance of a Page model.
     *
     * @return  self
     */ 
    public function setPage(Page $page): Manager
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Get instance of a Site model.
     *
     * @return  Site
     */ 
    public function getSite(): Site
    {   
        if (null === $this->site) {
            $this->site = $this->getPage()->site;
        }
        return $this->site;
    }

    /**
     * Set instance of a Site model.
     *
     * @param  Site  $site  Instance of a Site model.
     *
     * @return  self
     */ 
    public function setSite(Site $site): Manager
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Get the path where documents are cached
     *
     * @return  string
     */ 
    public function getDocumentsCacheDir()
    {
        return $this->documentsCacheDir;
    }

    /**
     * Set the path where documents are cached
     *
     * @param  string  $documentsCacheDir  The path where documents are cached
     *
     * @return  self
     */ 
    public function setDocumentsCacheDir(string $cacheDir)
    {
        $parts = explode('/', $cacheDir);
        # remove the last folder
        array_pop($parts);
        $this->documentsCacheDir = implode('/', $parts) . '/documents';

        return $this;
    }
}
