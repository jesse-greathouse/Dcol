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
        Schema::create('ai_training_file', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('ai_id');
            $table->integer('bytes');
            $table->integer('ai_created_at');
            $table->string('filename');
            $table->string('status')->comment('can be: uploaded, processed, pending, error, deleting or deleted.');
            $table->string('uri');
            $table->index('ai_id');
            $table->index('status');
            $table->index('uri');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_training_file');
    }
};
