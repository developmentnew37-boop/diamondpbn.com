<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_campaign_domain_replacements')) {
            Schema::table('schedule_campaign_domain_replacements', function (Blueprint $table) {
                if (! Schema::hasColumn('schedule_campaign_domain_replacements', 'previous_remote_id')) {
                    $table->string('previous_remote_id', 191)->nullable()->after('error');
                }
                if (! Schema::hasColumn('schedule_campaign_domain_replacements', 'previous_remote_url')) {
                    $table->text('previous_remote_url')->nullable()->after('previous_remote_id');
                }
                if (! Schema::hasColumn('schedule_campaign_domain_replacements', 'old_remote_cleanup_status')) {
                    $table->string('old_remote_cleanup_status', 32)->nullable()->after('previous_remote_url');
                }
                if (! Schema::hasColumn('schedule_campaign_domain_replacements', 'old_remote_cleanup_error')) {
                    $table->text('old_remote_cleanup_error')->nullable()->after('old_remote_cleanup_status');
                }
                if (! Schema::hasColumn('schedule_campaign_domain_replacements', 'old_remote_cleaned_at')) {
                    $table->timestamp('old_remote_cleaned_at')->nullable()->after('old_remote_cleanup_error');
                }
            });
        }

        if (Schema::hasTable('schedule_sidebar_campaign_domain_replacements')) {
            Schema::table('schedule_sidebar_campaign_domain_replacements', function (Blueprint $table) {
                if (! Schema::hasColumn('schedule_sidebar_campaign_domain_replacements', 'previous_remote_id')) {
                    $table->string('previous_remote_id', 191)->nullable()->after('error');
                }
                if (! Schema::hasColumn('schedule_sidebar_campaign_domain_replacements', 'previous_remote_url')) {
                    $table->text('previous_remote_url')->nullable()->after('previous_remote_id');
                }
                if (! Schema::hasColumn('schedule_sidebar_campaign_domain_replacements', 'old_remote_cleanup_status')) {
                    $table->string('old_remote_cleanup_status', 32)->nullable()->after('previous_remote_url');
                }
                if (! Schema::hasColumn('schedule_sidebar_campaign_domain_replacements', 'old_remote_cleanup_error')) {
                    $table->text('old_remote_cleanup_error')->nullable()->after('old_remote_cleanup_status');
                }
                if (! Schema::hasColumn('schedule_sidebar_campaign_domain_replacements', 'old_remote_cleaned_at')) {
                    $table->timestamp('old_remote_cleaned_at')->nullable()->after('old_remote_cleanup_error');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('schedule_campaign_domain_replacements')) {
            Schema::table('schedule_campaign_domain_replacements', function (Blueprint $table) {
                foreach (['previous_remote_id', 'previous_remote_url', 'old_remote_cleanup_status', 'old_remote_cleanup_error', 'old_remote_cleaned_at'] as $column) {
                    if (Schema::hasColumn('schedule_campaign_domain_replacements', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('schedule_sidebar_campaign_domain_replacements')) {
            Schema::table('schedule_sidebar_campaign_domain_replacements', function (Blueprint $table) {
                foreach (['previous_remote_id', 'previous_remote_url', 'old_remote_cleanup_status', 'old_remote_cleanup_error', 'old_remote_cleaned_at'] as $column) {
                    if (Schema::hasColumn('schedule_sidebar_campaign_domain_replacements', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
