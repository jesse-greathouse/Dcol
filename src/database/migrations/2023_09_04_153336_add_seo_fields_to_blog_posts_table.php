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
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->boolean('is_ai_model_trained')->default(false)->after('is_tweeted');
            $table->string('meta_description')->nullable()->default(null)->after('title');
            $table->string('focus_keyphrase')->nullable()->default(null)->after('meta_description');
            $table->index('is_ai_model_trained');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex('is_ai_model_trained_index');
            $table->dropColumn('is_ai_model_trained');
            $table->dropColumn('meta_description');
            $table->dropColumn('focus_keyphrase');
        });
    }
};
