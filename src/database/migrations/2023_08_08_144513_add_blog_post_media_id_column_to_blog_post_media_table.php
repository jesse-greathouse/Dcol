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
        Schema::table('blog_post_media', function (Blueprint $table) {
            $table->integer('blog_post_media_id')->nullable()->default(null)->index();
            $table->string('blog_media_id')->nullable()->default(null)->change();
            $table->string('blog_post_id')->nullable()->default(null)->change();
            $table->dropColumn('blog_media_type');
            $table->renameColumn('blog_post_type', 'blog_post_media_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_post_media', function (Blueprint $table) {
            $table->dropIndex('blog_post_media_id_index');
            $table->dropColumn('blog_post_media_id');
            $table->string('blog_media_id')->nullable(false)->change();
            $table->string('blog_post_id')->nullable(false)->change();
            $table->string('blog_media_type');
            $table->renameColumn('blog_post_media_type', 'blog_post_type');
        });
    }
};
