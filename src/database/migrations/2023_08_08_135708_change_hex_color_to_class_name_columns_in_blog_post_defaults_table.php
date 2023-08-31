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
        Schema::table('blog_post_defaults', function (Blueprint $table) {
            $table->renameColumn('hex_color_1', 'class_1');
            $table->renameColumn('hex_color_2', 'class_2');
            $table->renameColumn('hex_color_3', 'class_3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_post_defaults', function (Blueprint $table) {
            $table->renameColumn('class_1', 'hex_color_1');
            $table->renameColumn('class_2', 'hex_color_2');
            $table->renameColumn('class_3', 'hex_color_3');
        });
    }
};
