<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    use HasFactory;

    protected $guarded = [];

    const STATUS_CREATED = 'created';
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCEEDED = 'succeeded';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_VALIDATING_FILES = 'validating_files';

    /**
     * Get the training file for the ai model.
     */
    public function aiTrainingFile(): BelongsTo
    {
        return $this->belongsTo(AiTrainingFile::class);
    }
}
