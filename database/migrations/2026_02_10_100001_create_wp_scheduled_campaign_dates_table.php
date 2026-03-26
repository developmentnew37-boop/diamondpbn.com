<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-date post quantity distribution (user-controlled).
     */
    public function up(): void
    {
        Schema::create('wp_scheduled_campaign_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_scheduled_campaign_id')
                ->constrained('wp_scheduled_campaigns')
                ->cascadeOnDelete();
            $table->date('schedule_date')->index();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['wp_scheduled_campaign_id', 'schedule_date'], 'wp_sc_dates_campaign_date_uq');
            $table->index('wp_scheduled_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_scheduled_campaign_dates');
    }
};
