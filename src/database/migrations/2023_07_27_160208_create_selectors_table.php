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
        Schema::create('selectors', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('class');
            $table->unique('class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('selectors', function($table) {
            $table->dropUnique('class_unique');
        });
        Schema::dropIfExists('selectors');
    }
};
