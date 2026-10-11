<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wp_scheduled_campaign_domains')) {
            return;
        }

        if (Schema::hasIndex('wp_scheduled_campaign_domains', 'wp_sc_domains_domain_id_idx')) {
            return;
        }

        Schema::table('wp_scheduled_campaign_domains', function (Blueprint $table) {
            $table->index('domain_id', 'wp_sc_domains_domain_id_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wp_scheduled_campaign_domains')) {
            return;
        }

        if (! Schema::hasIndex('wp_scheduled_campaign_domains', 'wp_sc_domains_domain_id_idx')) {
            return;
        }

        Schema::table('wp_scheduled_campaign_domains', function (Blueprint $table) {
            $table->dropIndex('wp_sc_domains_domain_id_idx');
        });
    }
};
