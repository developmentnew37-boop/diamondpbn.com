<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sidebar_campaign_tasks', 'dispatch_generation')) {
            Schema::table('sidebar_campaign_tasks', function (Blueprint $table) {
                $table->unsignedInteger('dispatch_generation')->default(0)->after('attempt_count');
                $table->index(['id', 'dispatch_generation'], 'sct_id_dispatch_gen_idx');
            });
        }

        if (! Schema::hasColumn('hidden_links_campaigns_tasks', 'dispatch_generation')) {
            Schema::table('hidden_links_campaigns_tasks', function (Blueprint $table) {
                $table->unsignedInteger('dispatch_generation')->default(0)->after('attempt_count');
                $table->index(['id', 'dispatch_generation'], 'hlct_id_dispatch_gen_idx');
            });
        }

        if (! Schema::hasTable('sidebar_campaign_domain_replacements')) {
            Schema::create('sidebar_campaign_domain_replacements', function (Blueprint $table) {
                $table->id();
                $table->uuid('request_uuid')->unique();
                $table->unsignedBigInteger('sidebar_campaign_id')->nullable();
                $table->unsignedBigInteger('sidebar_campaign_task_id')->nullable();
                $table->unsignedBigInteger('sidebar_campaign_domain_id')->nullable();
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

                $table->index(['sidebar_campaign_id', 'created_at'], 'scdr_campaign_created_idx');
                $table->index(['sidebar_campaign_task_id', 'created_at'], 'scdr_task_created_idx');
                $table->index(['state', 'created_at'], 'scdr_state_created_idx');
            });
        }

        if (! Schema::hasTable('hidden_links_campaign_domain_replacements')) {
            Schema::create('hidden_links_campaign_domain_replacements', function (Blueprint $table) {
                $table->id();
                $table->uuid('request_uuid')->unique();
                $table->unsignedBigInteger('hidden_links_campaign_id')->nullable();
                $table->unsignedBigInteger('hidden_links_campaign_task_id')->nullable();
                $table->unsignedBigInteger('hidden_links_campaign_domain_id')->nullable();
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

                $table->index(['hidden_links_campaign_id', 'created_at'], 'hlcdr_campaign_created_idx');
                $table->index(['hidden_links_campaign_task_id', 'created_at'], 'hlcdr_task_created_idx');
                $table->index(['state', 'created_at'], 'hlcdr_state_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_links_campaign_domain_replacements');
        Schema::dropIfExists('sidebar_campaign_domain_replacements');

        if (Schema::hasColumn('hidden_links_campaigns_tasks', 'dispatch_generation')) {
            Schema::table('hidden_links_campaigns_tasks', function (Blueprint $table) {
                $table->dropIndex('hlct_id_dispatch_gen_idx');
                $table->dropColumn('dispatch_generation');
            });
        }

        if (Schema::hasColumn('sidebar_campaign_tasks', 'dispatch_generation')) {
            Schema::table('sidebar_campaign_tasks', function (Blueprint $table) {
                $table->dropIndex('sct_id_dispatch_gen_idx');
                $table->dropColumn('dispatch_generation');
            });
        }
    }
};
