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
        Schema::create('sidebar_campaigns', function (Blueprint $table) {

            $table->id();

            // Unique sidebar campaign identifier
            $table->string('campaign_no')->unique();   // e.g. SB-2026...

            // newly added 
            $table->string('report_token', 64)
                ->unique()
                ->nullable();

            // Domain category (optional filter)
            $table->foreignId('domain_category_id')
                ->nullable()
                ->constrained('domain_categories')
                ->nullOnDelete();

            // Owner
            $table->foreignId('admin_id')
                ->constrained('admins')
                ->cascadeOnDelete();

            // Sidebar total placements (your sidebarCount)
            $table->unsignedInteger('sidebar_count')->default(0);

            // Domain selection method
            $table->enum('domain_method', ['random', 'domain_set', 'manual'])
                ->default('random');

            // Campaign lifecycle
            $table->enum('status', [
                'queued',
                'running',
                'paused',
                'completed',
                'semi_failed',
                'failed',
                'cancelled'
            ])->default('queued');

            // Progress counters (1 domain = 1 sidebar publish action)
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            // indexes
            $table->index('status');
            $table->index('admin_id');
            $table->index('domain_category_id');
            $table->index('domain_method');
            $table->index('created_at');

            $table->index(['admin_id', 'status']);
            $table->index(['domain_category_id', 'status']);
            $table->index(['admin_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sidebar_campaigns');
    }
};
