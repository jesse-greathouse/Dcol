<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class BlogMedia extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the posts for the media.
     */
    public function blogPosts(): MorphToMany
    {
        return $this->morphToMany(BlogPost::class, 'blog_post_media');
    }
}
