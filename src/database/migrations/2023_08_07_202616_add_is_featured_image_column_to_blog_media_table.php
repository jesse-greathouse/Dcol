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
        Schema::table('blog_media', function (Blueprint $table) {
            $table->boolean('is_featured_image')->default(false)->index();
            $table->index('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_media', function (Blueprint $table) {
            $table->dropColumn('is_featured_image');
            $table->dropIndex('mime_type');
        });
    }
};
