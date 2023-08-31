<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Author extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the document that owns the author.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
