<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WordPress-native scheduled campaigns: posts are scheduled on remote WP with schedule date.
     */
    public function up(): void
    {
        Schema::create('wp_scheduled_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no')->unique();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('report_token', 64)->unique()->nullable();
            $table->foreignId('domain_category_id')->nullable()->constrained('domain_categories')->nullOnDelete();
            $table->foreignId('article_category_id')->nullable()->constrained('article_categories')->nullOnDelete();

            $table->date('schedule_from_date')->index();
            $table->date('schedule_to_date')->index();
            $table->unsignedInteger('total_targets');

            $table->enum('status', [
                'queued', 'running', 'paused', 'completed', 'semi_failed', 'failed', 'cancelled'
            ])->default('queued');

            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('admin_id');
            $table->index(['admin_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_scheduled_campaigns');
    }
};
