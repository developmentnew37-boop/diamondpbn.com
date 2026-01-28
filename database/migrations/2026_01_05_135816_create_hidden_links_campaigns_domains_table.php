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
        Schema::create('hidden_links_campaigns_domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hidden_links_campaigns_id')
                ->constrained('hidden_links_campaigns')
                ->cascadeOnDelete();

            $table->foreignId('domain_id')
                ->constrained('domains')
                ->cascadeOnDelete();

            $table->timestamps();

            // one domain only once per campaign
            $table->unique(
                ['hidden_links_campaigns_id', 'domain_id'],
                'hl_domains_campaign_domain_uq'
            );

            // indexes
            $table->index('hidden_links_campaigns_id');
            $table->index('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hidden_links_campaigns_domains');
    }
};
