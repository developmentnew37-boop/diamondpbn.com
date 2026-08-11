<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schedule_campaigns_posts', 'dispatch_generation')) {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                $table->unsignedInteger('dispatch_generation')->default(0)->after('attempt_count');
                $table->index(['id', 'dispatch_generation'], 'scp_id_dispatch_gen_idx');
            });
        }

        if (! Schema::hasColumn('schedule_sidebar_campaign_tasks', 'dispatch_generation')) {
            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
                $table->unsignedInteger('dispatch_generation')->default(0)->after('attempt_count');
                $table->index(['id', 'dispatch_generation'], 'ssct_id_dispatch_gen_idx');
            });
        }

        if (! Schema::hasTable('schedule_campaign_domain_replacements')) {
            Schema::create('schedule_campaign_domain_replacements', function (Blueprint $table) {
                $table->id();
                $table->uuid('request_uuid')->unique('sc_sched_cdr_req_uuid_uq');
                $table->unsignedBigInteger('schedule_campaign_id')->nullable();
                $table->unsignedBigInteger('schedule_campaign_post_id')->nullable();
                $table->unsignedBigInteger('schedule_campaign_domain_id')->nullable();
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

                $table->index(['schedule_campaign_id', 'created_at'], 'scdr_sched_campaign_created_idx');
                $table->index(['schedule_campaign_post_id', 'created_at'], 'scdr_sched_post_created_idx');
                $table->index(['state', 'created_at'], 'scdr_sched_state_created_idx');
            });
        }

        if (! Schema::hasTable('schedule_sidebar_campaign_domain_replacements')) {
            Schema::create('schedule_sidebar_campaign_domain_replacements', function (Blueprint $table) {
                $table->id();
                $table->uuid('request_uuid')->unique('sc_sched_sbcdr_req_uuid_uq');
                $table->unsignedBigInteger('schedule_sidebar_campaign_id')->nullable();
                $table->unsignedBigInteger('schedule_sidebar_campaign_task_id')->nullable();
                $table->unsignedBigInteger('schedule_sidebar_campaign_domain_id')->nullable();
                $table->unsignedBigInteger('old_domain_id')->nullable();
                $table->unsignedBigInteger('new_domain_id')->nullable();
                $table->string('old_hostname');
                $table->string('new_hostname');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->text('reason')->nullable();
                $table->string('previous_status', 32);
                $table->string('result_status', 32)->nullable();
                $table->json('health_snapshot')->nullable();
                $table->unsignedInteger('dispatch_generation');
                $table->string('state', 32)->default('pending');
                $table->text('error')->nullable();
                $table->timestamps();

                $table->foreign('old_domain_id', 'sscdr_old_domain_fk')->references('id')->on('domains')->nullOnDelete();
                $table->foreign('new_domain_id', 'sscdr_new_domain_fk')->references('id')->on('domains')->nullOnDelete();
                $table->foreign('admin_id', 'sscdr_admin_fk')->references('id')->on('admins')->nullOnDelete();

                $table->index(['schedule_sidebar_campaign_id', 'created_at'], 'sscdr_sched_campaign_created_idx');
                $table->index(['schedule_sidebar_campaign_task_id', 'created_at'], 'sscdr_sched_task_created_idx');
                $table->index(['state', 'created_at'], 'sscdr_sched_state_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_sidebar_campaign_domain_replacements');
        Schema::dropIfExists('schedule_campaign_domain_replacements');

        if (Schema::hasColumn('schedule_sidebar_campaign_tasks', 'dispatch_generation')) {
            Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
                $table->dropIndex('ssct_id_dispatch_gen_idx');
                $table->dropColumn('dispatch_generation');
            });
        }

        if (Schema::hasColumn('schedule_campaigns_posts', 'dispatch_generation')) {
            Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
                $table->dropIndex('scp_id_dispatch_gen_idx');
                $table->dropColumn('dispatch_generation');
            });
        }
    }
};
