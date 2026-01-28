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
        Schema::create('hidden_links_campaigns', function (Blueprint $table) {
            $table->id();

            // unique campaign reference (HL-2026-xxxx)
            $table->string('campaign_no')->unique();
                    // newly added 
            $table->string('report_token', 64)
                ->unique()
                ->nullable();

            // optional domain category filter
            $table->foreignId('domain_category_id')
                ->nullable()
                ->constrained('domain_categories')
                ->nullOnDelete();

            // owner (admin)
            $table->foreignId('admin_id')
                ->constrained('admins')
                ->cascadeOnDelete();

            // total hidden links count
            $table->unsignedInteger('sidebar_count')->default(0);

            // domain selection logic
            $table->enum('domain_method', ['random', 'domain_set', 'manual'])
                ->default('random');

            // campaign lifecycle
            $table->enum('status', [
                'queued',
                'running',
                'paused',
                'completed',
                'semi_failed',
                'failed',
                'cancelled',
            ])->default('queued');

            // progress counters
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);

            // timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            // indexes for reporting
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
        Schema::dropIfExists('hidden_links_campaigns');
    }
};
