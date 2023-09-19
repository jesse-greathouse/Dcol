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
        Schema::create('ai_model', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('ai_id');
            $table->integer('ai_created_at');
            $table->integer('ai_finished_at')->nullable()->default(null);
            $table->string('model');
            $table->string('fine_tuned_model')->nullable()->default(null);
            $table->string('status')->comment('can be: created, pending, running, succeeded, failed, or cancelled.');
            $table->json('result_files');
            $table->integer('trained_tokens')->nullable()->default(null);
            $table->string('error')->nullable()->default(null);
            $table->boolean('active')->default(true);
            $table->index('ai_id');
            $table->index('model');
            $table->index('fine_tuned_model');
            $table->index('status');
            $table->foreignId('ai_training_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_model');
    }
};
