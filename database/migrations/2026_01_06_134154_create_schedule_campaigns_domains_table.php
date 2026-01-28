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
        Schema::create('schedule_campaigns_domains', function (Blueprint $table) {

            $table->id();

            // 🔗 Link to schedule campaign
            $table->foreignId('schedule_campaign_id')
                ->constrained('schedule_campaigns')
                ->cascadeOnDelete();

            // 🌐 Selected domain
            $table->foreignId('domain_id')
                ->constrained('domains')
                ->cascadeOnDelete();

            $table->timestamps();

            // 🚫 Prevent same domain being added twice
            $table->unique(['schedule_campaign_id', 'domain_id']);

            // Indexes for performance
            $table->index('schedule_campaign_id');
            $table->index('domain_id');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_campaigns_domains');
    }
};
