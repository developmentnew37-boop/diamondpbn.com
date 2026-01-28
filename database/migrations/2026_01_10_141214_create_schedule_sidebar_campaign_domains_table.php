<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_sidebar_campaign_domains', function (Blueprint $table) {

            $table->id();

            // 🔗 Parent schedule
            $table->unsignedBigInteger('schedule_sidebar_campaign_id');

            // ✅ OWN domain reference (independent)
            $table->unsignedBigInteger('domain_id');

            $table->timestamps();

            // 🔑 FOREIGN KEYS (SHORT NAMES)
            $table->foreign(
                'schedule_sidebar_campaign_id',
                'ssc_domains_sched_campaign_fk'
            )->references('id')
                ->on('schedule_sidebar_campaigns')
                ->cascadeOnDelete();

            $table->foreign(
                'domain_id',
                'ssc_domains_domain_fk'
            )->references('id')
                ->on('domains')
                ->cascadeOnDelete();

            // 🔐 ONE DOMAIN PER SCHEDULE
            $table->unique(
                ['schedule_sidebar_campaign_id', 'domain_id'],
                'ssc_domains_campaign_domain_uq'
            );

            // INDEXES
            $table->index('schedule_sidebar_campaign_id', 'ssc_domains_sched_idx');
            $table->index('domain_id', 'ssc_domains_domain_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_sidebar_campaign_domains');
    }
};
