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
        Schema::create('sidebar_campaign_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sidebar_campaign_id')
                ->constrained('sidebar_campaigns')
                ->cascadeOnDelete();

            // order matters: 1..sidebar_count
            $table->unsignedInteger('sort_order')->default(0);

            $table->text('target_url');
            $table->string('anchor_keyword', 255);
            $table->boolean('nofollow')->default(false);

            $table->timestamps();

            // prevent duplicates in same campaign order
            $table->unique(['sidebar_campaign_id', 'sort_order']);

            // indexes
            $table->index('sidebar_campaign_id');
            $table->index('nofollow');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sidebar_campaign_links');
    }
};
