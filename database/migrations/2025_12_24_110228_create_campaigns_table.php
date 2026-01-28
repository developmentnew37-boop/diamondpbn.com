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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();

            // Unique campaign identifier
            $table->string('campaign_no')->unique();

            // newly added 
            $table->string('report_token', 64)
                ->unique()
                ->nullable();

            // Optional domain category filter
            $table->foreignId('domain_category_id')
                ->nullable()
                ->constrained('domain_categories')
                ->nullOnDelete();

            // Optional article category filter (affects search)
            $table->foreignId('article_category_id')
                ->nullable()
                ->constrained('article_categories')
                ->nullOnDelete();

            // Owner
            $table->foreignId('admin_id')
                ->constrained('admins')
                ->cascadeOnDelete();

            // Article selection type
            $table->enum('article_type', ['own_article', 'system_article']);

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

            $table->boolean('is_sticky_campaign')
                ->default(false)
                ->index();


            // Progress counters (1 domain = 1 article)
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            // ✅ Indexes for fast search/filter
            $table->index('status');
            $table->index('admin_id');
            $table->index('domain_category_id');
            $table->index('article_type');
            $table->index('created_at');

            // ✅ Composite indexes (admin panel filters)
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
        Schema::dropIfExists('campaigns');
    }
};
