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
        Schema::create('page_selector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('selector_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_selector', function($table) {
            $table->dropForeign('page_id_foreign');
            $table->dropForeign('selector_id_foreign');
        });
        Schema::dropIfExists('page_selector');
    }
};
