<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Mopdel\Blog;

class BlogAuth extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * Get the blogs for the blogAuth.
     */
    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class);
    }
}
