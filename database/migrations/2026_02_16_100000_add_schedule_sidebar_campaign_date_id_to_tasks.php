<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Link task to the date row it was created from so the report can show
     * the preserved schedule_date from schedule_sidebar_campaign_dates (never updated by jobs).
     */
    public function up(): void
    {
        Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_sidebar_campaign_date_id')->nullable()->after('schedule_sidebar_campaign_link_id');
            $table->foreign('schedule_sidebar_campaign_date_id', 'ssc_tasks_campaign_date_id_fk')
                ->references('id')->on('schedule_sidebar_campaign_dates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
            $table->dropForeign('ssc_tasks_campaign_date_id_fk');
            $table->dropColumn('schedule_sidebar_campaign_date_id');
        });
    }
};
