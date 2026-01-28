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
        Schema::create('schedule_sidebar_campaigns', function (Blueprint $table) {
            $table->id();

            // 🔗 Reference actual sidebar campaign
            $table->string('campaign_no')->unique();
            // report token
            $table->string('report_token', 64)
                ->unique()
                ->nullable();
            // Owner
            $table->foreignId('admin_id')
                ->constrained('admins')
                ->cascadeOnDelete();

            // Optional domain category filter (UI driven)
            $table->foreignId('domain_category_id')
                ->nullable()
                ->constrained('domain_categories')
                ->nullOnDelete();

            // 📅 Schedule window (DAY-BASED)
            $table->date('schedule_from_date')->index();
            $table->date('schedule_to_date')->index();

            // Lifecycle
            $table->enum('status', [
                'queued',
                'running',
                'paused',
                'completed',
                'semi_failed',
                'failed',
                'cancelled'
            ])->default('queued');

            // Total sidebar tasks to be scheduled
            $table->unsignedInteger('total_targets');

            // Progress counters
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            // Indexes (same philosophy as schedule_campaigns)
            $table->index('status');
            $table->index('admin_id');
            $table->index(['admin_id', 'status']);
            $table->index(
                ['schedule_from_date', 'schedule_to_date'],
                'ssc_from_to_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_sidebar_campaigns');
    }
};
