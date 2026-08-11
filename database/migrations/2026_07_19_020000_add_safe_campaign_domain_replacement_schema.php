<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_posts', function (Blueprint $table) {
            $table->string('delivery_state', 32)->default('not_attempted')->index();
            $table->string('last_failure_code', 64)->nullable();
            $table->unsignedInteger('dispatch_generation')->default(0);
            $table->index(['id', 'dispatch_generation']);
        });

        DB::table('campaign_posts')
            ->where(function ($query) {
                $query->where('status', 'success')
                    ->orWhereNotNull('remote_id')
                    ->orWhereNotNull('remote_title')
                    ->orWhereNotNull('remote_url')
                    ->orWhereNotNull('published_at');
            })
            ->update(['delivery_state' => 'remote_created']);

        DB::table('campaign_posts')
            ->where('delivery_state', 'not_attempted')
            ->where(function ($query) {
                $query->where('status', '!=', 'queued')
                    ->orWhere('attempt_count', '>', 0)
                    ->orWhereNotNull('last_error')
                    ->orWhereNotNull('next_retry_at')
                    ->orWhereNotNull('locked_at')
                    ->orWhereNotNull('locked_until')
                    ->orWhereNotNull('lock_token');
            })
            ->update(['delivery_state' => 'remote_unknown']);

        Schema::create('campaign_domain_replacements', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique();
            // Audit history must survive deletion of campaign working rows.
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('campaign_post_id')->nullable()->constrained('campaign_posts')->nullOnDelete();
            $table->foreignId('campaign_domain_id')->nullable()->constrained('campaign_domains')->nullOnDelete();
            $table->foreignId('old_domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->foreignId('new_domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->string('old_hostname');
            $table->string('new_hostname');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('previous_status', 32);
            $table->string('result_status', 32)->nullable();
            $table->json('health_snapshot')->nullable();
            $table->unsignedInteger('dispatch_generation');
            $table->string('state', 32)->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'created_at']);
            $table->index(['campaign_post_id', 'created_at']);
            $table->index(['campaign_domain_id', 'created_at']);
            $table->index(['state', 'created_at']);
            $table->index(['old_domain_id', 'new_domain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_domain_replacements');

        Schema::table('campaign_posts', function (Blueprint $table) {
            $table->dropIndex(['delivery_state']);
            $table->dropIndex(['id', 'dispatch_generation']);
            $table->dropColumn([
                'delivery_state',
                'last_failure_code',
                'dispatch_generation',
            ]);
        });
    }
};
