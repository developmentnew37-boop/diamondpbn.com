<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_scheduled_campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wp_scheduled_campaign_id')
                ->constrained('wp_scheduled_campaigns')
                ->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['wp_scheduled_campaign_id', 'domain_id'], 'wp_sc_domains_campaign_domain_uq');
            $table->index('wp_scheduled_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_scheduled_campaign_domains');
    }
};
