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
        Schema::table('ai_model', function (Blueprint $table) {
            $table->string('object')->default('fine_tuning.job');
            $table->string('organization_id')->nullable()->default(null);
            $table->string('training_file')->nullable()->default(null);
            $table->string('validation_file')->nullable()->default(null);
            $table->json('hyperparameters')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_model', function (Blueprint $table) {
            $table->dropColumn('object');
            $table->dropColumn('organization_id');
            $table->dropColumn('training_file');
            $table->dropColumn('validation_file');
            $table->dropColumn('hyperparameters');
        });
    }
};
