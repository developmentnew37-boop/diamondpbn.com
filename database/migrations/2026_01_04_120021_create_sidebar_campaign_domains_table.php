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
        if (Schema::hasTable('sidebar_campaign_domains')) {
            return;
        }

        Schema::create('sidebar_campaign_domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sidebar_campaign_id')
                ->constrained('sidebar_campaigns')
                ->cascadeOnDelete();

            $table->foreignId('domain_id')
                ->constrained('domains')
                ->cascadeOnDelete();

            $table->timestamps();

            // ✅ one domain only once per sidebar campaign
            $table->unique(['sidebar_campaign_id', 'domain_id']);

            // indexes for fast joins
            $table->index('sidebar_campaign_id');
            $table->index('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sidebar_campaign_domains');
    }
};
