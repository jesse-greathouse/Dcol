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
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('title');
            $table->dateTime('publication_date')
                ->nullable()
                ->default(null);
            $table->longText('tweet');
            $table->longText('blurb');
            $table->longText('writeup');
            $table->longText('html_writeup');
            $table->longText('summary');
            $table->foreignId('document_id')->constrain();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
