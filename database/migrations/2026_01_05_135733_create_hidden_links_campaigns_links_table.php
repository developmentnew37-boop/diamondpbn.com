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
        Schema::create('hidden_links_campaigns_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hidden_links_campaigns_id')
                ->constrained('hidden_links_campaigns')
                ->cascadeOnDelete();

            // exact pairing order (1..sidebar_count)
            $table->unsignedInteger('sort_order');

            $table->text('target_url');
            $table->string('anchor_keyword', 255);
            $table->boolean('nofollow')->default(false);

            $table->timestamps();

            // one link per position per campaign
            $table->unique(
                ['hidden_links_campaigns_id', 'sort_order'],
                'hl_links_campaign_sort_uq'
            );

            // indexes
            $table->index('hidden_links_campaigns_id');
            $table->index('nofollow');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hidden_links_campaigns_links');
    }
};
