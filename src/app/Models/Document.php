<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the content for the document.
     */
    public function content(): HasOne
    {
        return $this->hasOne(Content::class);
    }

    /**
     * Get the page for the document.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get the document type.
     */
    public function type(): HasOne
    {
        return $this->hasOne(Type::class);
    }

    /**
     * Get the tags for the document.
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * Get the blog posts for the document.
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    /**
     * Get the authors for the document.
     */
    public function authors(): HasMany
    {
        return $this->hasMany(Author::class);
    }

    /**
     * Returns decoded raw_text
     *
     * @return string
     */
    public function decodeRawText(): string 
    {
        return base64_decode($this->raw_text);
    }

    /**
     * Encodes a string to base64
     *
     * @param string $rawText
     * @return string
     */
    public function encodeRawText(string $rawText): string
    {
        return base64_encode($rawText);
    }

    /**
     * Overloads the save method.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        # A check to make sure raw_text is base64 encoded before save.
        $this->verifyRawTextBase64();

        return parent::save($options);
    }

    /**
     * Verify that raw_text is propery encoded
     *
     * @return void
     */
    protected function verifyRawTextBase64(): void 
    {
        if (base64_encode(base64_decode($this->raw_text, true)) !== $this->raw_text){
            $this->raw_text = $this->encodeRawText($this->raw_text);
        }
    }

}
