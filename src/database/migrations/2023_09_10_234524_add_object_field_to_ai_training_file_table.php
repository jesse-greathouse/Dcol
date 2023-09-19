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
        Schema::table('ai_training_file', function (Blueprint $table) {
            $table->string('object')->default('file');
            $table->string('purpose');
            $table->string('status_details')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_training_file', function (Blueprint $table) {
            $table->dropColumn('object');
            $table->dropColumn('purpose');
            $table->dropColumn('status_details');
        });
    }
};
