<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

use App\Models\BlogAuth;

class Blog extends Model
{
    use HasFactory;

    /**
     * Get the sites for this blog.
     *
     * @return BelongsToMany
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class);
    }

    /**
     * Get The Authentication Implementation for this blog.
     *
     * @return BelongsTo
     */
    public function blogAuth(): BelongsTo
    {
        return $this->belongsTo(BlogAuth::class, 'id');
    }

    /**
     * Get the blog posts for this blog.
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

}
