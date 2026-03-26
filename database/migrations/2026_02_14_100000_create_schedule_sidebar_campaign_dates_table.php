<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-date sidebar link quantity for Schedule Sidebar Campaigns (like Schedule Campaign).
     */
    public function up(): void
    {
        Schema::create('schedule_sidebar_campaign_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_sidebar_campaign_id');
            $table->foreign('schedule_sidebar_campaign_id', 'ssc_dates_campaign_id_fk')
                ->references('id')->on('schedule_sidebar_campaigns')
                ->cascadeOnDelete();
            $table->date('schedule_date')->index();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['schedule_sidebar_campaign_id', 'schedule_date'], 'ssc_dates_campaign_date_uq');
            $table->index('schedule_sidebar_campaign_id', 'ssc_dates_campaign_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_sidebar_campaign_dates');
    }
};
