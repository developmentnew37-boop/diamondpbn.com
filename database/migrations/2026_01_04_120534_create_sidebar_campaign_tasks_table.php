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
        if (Schema::hasTable('sidebar_campaign_tasks')) {
            return;
        }

        Schema::create('sidebar_campaign_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sidebar_campaign_id')
                ->constrained('sidebar_campaigns')
                ->cascadeOnDelete();

            $table->foreignId('sidebar_campaign_domain_id')
                ->constrained('sidebar_campaign_domains')
                ->cascadeOnDelete();

            // ✅ NEW: exact link this domain must post
            $table->foreignId('sidebar_campaign_link_id')
                ->constrained('sidebar_campaign_links')
                ->cascadeOnDelete();

            $table->enum('status', ['queued', 'publishing', 'success', 'failed'])
                ->default('queued');

            // snapshot (optional – now only 1 link, but still useful for audit)
            $table->json('links_payload')->nullable();

            // remote result
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('remote_response')->nullable();
            $table->timestamp('published_at')->nullable();

            // retry + error info
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            // timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // locking
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamp('locked_until')->nullable();

            $table->timestamps();

            // ✅ one task per domain per campaign
            $table->unique(
                ['sidebar_campaign_id', 'sidebar_campaign_domain_id'],
                'sb_tasks_campaign_domain_uq'
            );

            // ✅ one link used once per campaign
            $table->unique(
                ['sidebar_campaign_id', 'sidebar_campaign_link_id'],
                'sb_tasks_campaign_link_uq'
            );

            // indexes for workers
            $table->index(
                ['sidebar_campaign_id', 'status'],
                'sb_tasks_campaign_status_idx'
            );

            $table->index(
                ['status', 'next_retry_at'],
                'sb_tasks_status_retry_idx'
            );

            $table->index('next_retry_at', 'sb_tasks_next_retry_idx');
            $table->index('locked_until', 'sb_tasks_locked_until_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sidebar_campaign_tasks');
    }
};
