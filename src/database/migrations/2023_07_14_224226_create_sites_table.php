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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('domain_name');
            $table->integer('crawl_count');
            $table->timestamp('last_crawled_at')->nullable();

            $table->unique('domain_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function($table) {
            $table->dropUnique('domain_name_unique');
        });
        Schema::dropIfExists('sites');
    }
};
