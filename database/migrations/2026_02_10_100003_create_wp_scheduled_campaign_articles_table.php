<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_scheduled_campaign_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_scheduled_campaign_id')
                ->constrained('wp_scheduled_campaigns')
                ->cascadeOnDelete();
            $table->foreignId('article_id')
                ->nullable()
                ->constrained('articles')
                ->nullOnDelete();
            $table->text('keyword')->nullable();
            $table->text('url')->nullable();
            $table->enum('keyword_type', ['single', 'json'])->default('single');
            $table->enum('url_type', ['single', 'json'])->default('single');
            $table->boolean('nofollow')->default(false);
            $table->string('media')->nullable();
            $table->timestamps();

            $table->unique(['wp_scheduled_campaign_id', 'article_id'], 'wp_sc_articles_campaign_article_uq');
            $table->index('wp_scheduled_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_scheduled_campaign_articles');
    }
};
