<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-date post quantity for Schedule Campaigns (like WP Scheduled).
     */
    public function up(): void
    {
        Schema::create('schedule_campaign_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_campaign_id')
                ->constrained('schedule_campaigns')
                ->cascadeOnDelete();
            $table->date('schedule_date')->index();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['schedule_campaign_id', 'schedule_date'], 'sc_dates_campaign_date_uq');
            $table->index('schedule_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_campaign_dates');
    }
};
