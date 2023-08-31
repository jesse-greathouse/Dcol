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
        Schema::create('blog_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('blog_media_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_post_media');
    }
};
