<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Report must always show the date the task/post was originally scheduled for,
     * even after it goes live (jobs must never update this column).
     */
    public function up(): void
    {
        Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
            $table->timestamp('original_schedule_at')->nullable()->after('schedule_at');
        });
        Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
            $table->timestamp('original_schedule_at')->nullable()->after('schedule_at');
        });

        // Backfill so existing rows show current schedule_at as their "original" (best we can do)
        DB::table('schedule_sidebar_campaign_tasks')->whereNull('original_schedule_at')->update([
            'original_schedule_at' => DB::raw('schedule_at'),
        ]);
        DB::table('schedule_campaigns_posts')->whereNull('original_schedule_at')->update([
            'original_schedule_at' => DB::raw('schedule_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
            $table->dropColumn('original_schedule_at');
        });
        Schema::table('schedule_campaigns_posts', function (Blueprint $table) {
            $table->dropColumn('original_schedule_at');
        });
    }
};
