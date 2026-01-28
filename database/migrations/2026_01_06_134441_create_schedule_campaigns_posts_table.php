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
        Schema::create('schedule_campaigns_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_campaign_id')
                ->constrained('schedule_campaigns')
                ->cascadeOnDelete();

            $table->foreignId('schedule_campaign_article_id')
                ->constrained('schedule_campaigns_articles')
                ->cascadeOnDelete();

            $table->foreignId('schedule_campaign_domain_id')
                ->constrained('schedule_campaigns_domains')
                ->cascadeOnDelete();

            $table->timestamp('schedule_at')->index();

            $table->enum('status', ['queued', 'publishing', 'success', 'failed'])
                ->default('queued');

            $table->unsignedInteger('attempt_count')->default(0);
            // $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            // $table->timestamp('locked_until')->nullable();

            $table->string('remote_id')->nullable();
            $table->string('remote_title')->nullable();
            $table->string('remote_url')->nullable();
            $table->integer('http_status')->nullable();
            $table->string('remote_status')->nullable();
            $table->json('remote_response')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // ✅ ONE short unique index (THIS IS THE FIX)
            $table->unique(
                ['schedule_campaign_article_id', 'schedule_campaign_domain_id'],
                'sc_posts_article_domain_uq'
            );

            $table->index(['status', 'schedule_at']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_campaigns_posts');
    }
};
