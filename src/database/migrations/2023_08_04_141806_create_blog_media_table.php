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
        Schema::create('blog_media', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('media_id')->index();
            $table->string('slug')->index();
            $table->longText('url');
            $table->longText('source_url');
            $table->integer('author')->index();
            $table->dateTime('publication_date')
                ->nullable()
                ->default(null);
            $table->string('type')->index();
            $table->string('media_type')->index();
            $table->string('mime_type');
            $table->longText('caption');
            $table->longText('descriptrion');
            $table->longText('media_details');
            $table->boolean('is_published')->index();
            $table->foreignId('blog_id');
            $table->foreignId('document_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_media');
    }
};
