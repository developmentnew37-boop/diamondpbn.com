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
        Schema::create('article_set_items', function (Blueprint $table) {
            
            $table->id();

            // Article Set FK
            $table->foreignId('article_set_id')
                ->constrained('article_sets')
                ->cascadeOnDelete();

            // Article FK
            $table->foreignId('article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            // Prevent duplicate article in same set
            $table->unique(['article_set_id', 'article_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_set_items');
    }
};
