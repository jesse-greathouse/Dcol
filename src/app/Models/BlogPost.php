<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class BlogPost extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the media for the blog post.
     */
    public function blogMedia(): MorphToMany
    {
        return $this->morphToMany(BlogMedia::class, 'blog_post_media');
    }

    /**
     * Get the document for the blog post.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the blog for the blog post.
     */
    public function blog(): BelongsTo
    {
        return $this->belongsTo(Blog::class);
    }
}
