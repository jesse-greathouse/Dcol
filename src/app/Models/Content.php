<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Content extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the document associated with the content.
     */
    public function document(): HasOne
    {
        return $this->hasOne(Document::class);
    }
}
