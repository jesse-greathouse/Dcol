<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use HasFactory;

    /**
     * Get the pages for the site.
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Get the blogs for the site.
     */
    public function blogs(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class);
    }

    /**
     * Get the Blog Posts for the site.
     */
    public function blogPosts(): HasManyThrough
    {
        return $this->hasManyThrough(Blog::class, BlogPost::class);
    }

}
