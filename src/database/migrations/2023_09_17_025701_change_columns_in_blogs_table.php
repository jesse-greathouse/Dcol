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
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn('fine_tuned_model');
            $table->dropColumn('fine_tuned_model_suffix');
            $table->foreignId('ai_model_id')->nullable()->default(null)->after('blog_auth_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->string('fine_tuned_model')->nullable()->default(null);
            $table->string('fine_tuned_model_suffix')->nullable()->default(null)->after('fine_tuned_model');
            $table->dropForeign('ai_model_id');
        });
    }
};
