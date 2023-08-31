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
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('post_id')->index();
            $table->string('slug')->index();
            $table->longText('url');
            $table->integer('author')->index();
            $table->dateTime('publication_date')
                ->nullable()
                ->default(null);
            $table->string('type')->index();
            $table->string('title');
            $table->longText('content');
            $table->integer('category')->index();
            $table->integer('featured_media')->index();
            $table->boolean('is_published')->index();
            $table->boolean('is_tweeted')->index();
            $table->foreignId('blog_id');
            $table->foreignId('document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
