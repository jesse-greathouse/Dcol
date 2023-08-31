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
            $table->string('blog_post_type');
            $table->string('blog_media_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_post_media', function (Blueprint $table) {
            $table->dropColumn('blog_post_type');
            $table->dropColumn('blog_media_type');
        });
    }
};
