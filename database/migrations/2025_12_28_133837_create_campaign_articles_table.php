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
        Schema::create('campaign_articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();

            // nullOnDelete: permanently deleting a library article must not remove campaign history
            $table->foreignId('article_id')
                ->nullable()
                ->constrained('articles')
                ->nullOnDelete();

            // store raw string OR json string (array)
            $table->text('keyword')->nullable();
            $table->text('url')->nullable();

            // explicit type flags (no guessing)
            $table->enum('keyword_type', ['single', 'json'])->default('single');
            $table->enum('url_type', ['single', 'json'])->default('single');

            $table->string('media')->nullable();
            $table->boolean('nofollow')->default(false);
            $table->timestamps();

            // prevent duplicate article selection in same campaign
            $table->unique(['campaign_id', 'article_id']);

            // indexes
            $table->index('campaign_id');
            $table->index('article_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_articles');
    }
};
