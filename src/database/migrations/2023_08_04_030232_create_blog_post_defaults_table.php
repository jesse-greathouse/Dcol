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
        Schema::create('blog_post_defaults', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('author')->index();
            $table->integer('category')->index();
            $table->longText('template')
                ->nullable()
                ->default(null);
            $table->integer('featured_media');
            $table->foreignId('site_id');
            $table->foreignId('blog_id');
            $table->unique(['site_id', 'blog_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_post_defaults');
    }
};
