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
            $table->string('openai_secret')->nullable()->default(null);
            $table->string('x_api_key')->nullable()->default(null);
            $table->string('x_api_secret')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn('openai_secret');
            $table->dropColumn('x_api_key');
            $table->dropColumn('x_api_secret');
        });
    }
};
