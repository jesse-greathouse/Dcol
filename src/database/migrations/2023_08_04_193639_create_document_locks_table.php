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
        Schema::create('document_locks', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('is_locked')->index()->default(true);
            $table->foreignId('document_id')->constrain();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_locks');
    }
};
