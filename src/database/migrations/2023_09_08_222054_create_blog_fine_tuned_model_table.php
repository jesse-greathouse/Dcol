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
        Schema::create('blog_ai_model', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_id');
            $table->foreignId('ai_model_id');
            $table->unique(['blog_id', 'ai_model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_ai_model');
    }
};
