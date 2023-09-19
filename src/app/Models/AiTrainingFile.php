<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiTrainingFile extends Model
{
    use HasFactory;

    const PURPOSE = 'fine-tune';
    const STATUS_UPLOADED = 'uploaded';
    const STATUS_PROCESSED = 'processed';
    const STATUS_PENDING = 'pending';
    const STATUS_ERROR = 'error';
    const STATUS_DELETING = 'deleting';
    const STATUS_DELETED = 'deleted';

    protected $guarded = [];
}
