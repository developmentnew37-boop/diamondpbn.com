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
        // Schema::create('hidden_links_campaigns_tasks', function (Blueprint $table) {
        //     $table->id();

        //     $table->foreignId('hidden_links_campaigns_id')
        //         ->constrained('hidden_links_campaigns')
        //         ->cascadeOnDelete();

        //     $table->foreignId('hidden_links_campaigns_domain_id')
        //         ->constrained('hidden_links_campaigns_domains')
        //         ->cascadeOnDelete();

        //     $table->foreignId('hidden_links_campaigns_link_id')
        //         ->constrained('hidden_links_campaigns_links')
        //         ->cascadeOnDelete();

        //     // job state
        //     $table->enum('status', ['queued', 'publishing', 'success', 'failed'])
        //         ->default('queued');

        //     // snapshot (single keyword+url row)
        //     $table->json('links_payload')->nullable();

        //     // remote response
        //     $table->string('remote_id')->nullable();
        //     $table->string('remote_url')->nullable();
        //     $table->unsignedSmallInteger('http_status')->nullable();
        //     $table->json('remote_response')->nullable();
        //     $table->timestamp('published_at')->nullable();

        //     // retry system
        //     $table->unsignedTinyInteger('attempt_count')->default(0);
        //     $table->unsignedTinyInteger('max_attempts')->default(5);
        //     $table->text('last_error')->nullable();
        //     $table->timestamp('next_retry_at')->nullable();

        //     // timing
        //     $table->timestamp('started_at')->nullable();
        //     $table->timestamp('finished_at')->nullable();

        //     // locking (multi-worker safe)
        //     $table->timestamp('locked_at')->nullable();
        //     $table->string('lock_token')->nullable();
        //     $table->timestamp('locked_until')->nullable();

        //     $table->timestamps();

        //     // constraints
        //     $table->unique(
        //         ['hidden_links_campaigns_id', 'hidden_links_campaigns_domain_id'],
        //         'hl_tasks_campaign_domain_uq'
        //     );

        //     $table->unique(
        //         ['hidden_links_campaigns_id', 'hidden_links_campaigns_link_id'],
        //         'hl_tasks_campaign_link_uq'
        //     );

        //     // worker indexes
        //     $table->index(
        //         ['hidden_links_campaigns_id', 'status'],
        //         'hl_tasks_campaign_status_idx'
        //     );

        //     $table->index(
        //         ['status', 'next_retry_at'],
        //         'hl_tasks_status_retry_idx'
        //     );

        //     $table->index('next_retry_at', 'hl_tasks_next_retry_idx');
        //     $table->index('locked_until', 'hl_tasks_locked_until_idx');
        // });
        Schema::create('hidden_links_campaigns_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hidden_links_campaigns_id')
                ->constrained('hidden_links_campaigns', 'id', 'hl_tasks_campaign_fk')
                ->cascadeOnDelete();

            $table->foreignId('hidden_links_campaigns_domain_id')
                ->constrained(
                    'hidden_links_campaigns_domains',
                    'id',
                    'hl_tasks_campaign_domain_fk'
                )
                ->cascadeOnDelete();

            $table->foreignId('hidden_links_campaigns_link_id')
                ->constrained(
                    'hidden_links_campaigns_links',
                    'id',
                    'hl_tasks_campaign_link_fk'
                )
                ->cascadeOnDelete();

            // job state
            $table->enum('status', ['queued', 'publishing', 'success', 'failed'])
                ->default('queued');

            $table->json('links_payload')->nullable();

            // remote response
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('remote_response')->nullable();
            $table->timestamp('published_at')->nullable();

            // retry system
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

            // unique constraints
            $table->unique(
                ['hidden_links_campaigns_id', 'hidden_links_campaigns_domain_id'],
                'hl_tasks_campaign_domain_uq'
            );

            $table->unique(
                ['hidden_links_campaigns_id', 'hidden_links_campaigns_link_id'],
                'hl_tasks_campaign_link_uq'
            );

            // worker indexes
            $table->index(
                ['hidden_links_campaigns_id', 'status'],
                'hl_tasks_campaign_status_idx'
            );

            $table->index(
                ['status', 'next_retry_at'],
                'hl_tasks_status_retry_idx'
            );

            $table->index('next_retry_at', 'hl_tasks_next_retry_idx');
            $table->index('locked_until', 'hl_tasks_locked_until_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hidden_links_campaigns_tasks');
    }
};
