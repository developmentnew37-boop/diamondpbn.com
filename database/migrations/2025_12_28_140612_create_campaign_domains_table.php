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

        Schema::create('campaign_domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();

            $table->foreignId('domain_id')
                ->constrained('domains')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')
                ->after('domain_id')
                ->index();

            $table->timestamps();

            // ✅ no duplicate domain inside a campaign
            $table->unique(['campaign_id', 'domain_id']);

            // ✅ indexes for fast queries
            $table->index('campaign_id');
            $table->index('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_domains');
    }
};
