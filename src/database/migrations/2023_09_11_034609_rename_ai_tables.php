<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('ai_training_file', 'ai_training_files');
        Schema::rename('ai_model', 'ai_models');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('ai_models', 'ai_model');
        Schema::rename('ai_training_files', 'ai_training_file');
    }
};
