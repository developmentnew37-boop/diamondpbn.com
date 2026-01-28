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
        Schema::create('campaign_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();

            $table->foreignId('campaign_domain_id')
                ->constrained('campaign_domains')
                ->cascadeOnDelete();

            $table->foreignId('campaign_article_id')
                ->constrained('campaign_articles')
                ->cascadeOnDelete();

            $table->enum('status', ['queued', 'publishing', 'success', 'failed'])
                ->default('queued');
            $table->boolean('is_sticky')
                ->default(false)
                ->index();

            // ✅ Remote publish results
            $table->string('remote_id')->nullable();
            $table->string('remote_title')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamp('published_at')->nullable();

            // ✅ Retry + error info
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            // ✅ Locking to prevent two workers publishing same row
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();

            // ✅ (Recommended) Lock expiry so dead workers don't block forever
            $table->timestamp('locked_until')->nullable();

            $table->timestamps();

            // ✅ Hard safety rule: one domain-row paired with one article-row (1 post)
            $table->unique(['campaign_domain_id', 'campaign_article_id']);

            // ✅ Worker performance indexes
            $table->index(['campaign_id', 'status']);
            $table->index(['status', 'next_retry_at']); // fast pickup for queued/failed retries
            $table->index('next_retry_at');
            $table->index('locked_at');
            $table->index('locked_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_posts');
    }
};
