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
        Schema::create('schedule_sidebar_campaign_tasks', function (Blueprint $table) {

            $table->id();

            // 🔗 Parent schedule
            $table->unsignedBigInteger('schedule_sidebar_campaign_id');

            // ✅ OWNED domain & link (independent)
            $table->unsignedBigInteger('schedule_sidebar_campaign_domain_id');
            $table->unsignedBigInteger('schedule_sidebar_campaign_link_id');

            // ⏰ Exact execution time
            $table->timestamp('schedule_at');

            // Task lifecycle
            $table->enum('status', [
                'queued',
                'publishing',
                'success',
                'failed'
            ])->default('queued');

            // Retry & error handling
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();

            // Locking
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();

            // Remote response snapshot
            $table->string('remote_id')->nullable();
            $table->integer('http_status')->nullable();
            $table->json('remote_response')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // 🔑 FOREIGN KEYS (SHORT NAMES)
            $table->foreign(
                'schedule_sidebar_campaign_id',
                'ssc_tasks_sched_campaign_fk'
            )->references('id')
                ->on('schedule_sidebar_campaigns')
                ->cascadeOnDelete();

            $table->foreign(
                'schedule_sidebar_campaign_domain_id',
                'ssc_tasks_sched_domain_fk'
            )->references('id')
                ->on('schedule_sidebar_campaign_domains')
                ->cascadeOnDelete();

            $table->foreign(
                'schedule_sidebar_campaign_link_id',
                'ssc_tasks_sched_link_fk'
            )->references('id')
                ->on('schedule_sidebar_campaign_links')
                ->cascadeOnDelete();

            // 🔐 ONE DOMAIN + ONE LINK PER TASK
            $table->unique(
                [
                    'schedule_sidebar_campaign_id',
                    'schedule_sidebar_campaign_domain_id',
                    'schedule_sidebar_campaign_link_id'
                ],
                'ssc_tasks_campaign_domain_link_uq'
            );

            // 📌 INDEXES
            $table->index(['status', 'schedule_at'], 'ssc_tasks_status_at_idx');
            $table->index(['status', 'next_retry_at'], 'ssc_tasks_status_retry_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_sidebar_campaign_tasks');
    }
};
