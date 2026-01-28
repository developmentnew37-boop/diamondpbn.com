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
        Schema::create('schedule_sidebar_campaign_links', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('schedule_sidebar_campaign_id');

            // ✅ OWN LINK DATA (INDEPENDENT)
            $table->text('target_url');
            $table->string('anchor_keyword', 255);
            $table->boolean('nofollow')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // 🔑 FK
            $table->foreign(
                'schedule_sidebar_campaign_id',
                'ssc_links_sched_campaign_fk'
            )->references('id')
                ->on('schedule_sidebar_campaigns')
                ->cascadeOnDelete();

            // 🔐 One link per position
            $table->unique(
                ['schedule_sidebar_campaign_id', 'sort_order'],
                'ssc_links_campaign_sort_uq'
            );

            // INDEXES
            $table->index('schedule_sidebar_campaign_id', 'ssc_links_sched_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_sidebar_campaign_links');
    }
};
