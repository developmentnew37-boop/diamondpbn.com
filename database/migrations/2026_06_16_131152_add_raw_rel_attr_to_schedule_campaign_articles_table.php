<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds raw_rel_attr column to schedule_campaigns_articles table.
     * This allows custom rel values (external, bookmark, etc.) beyond the 5 hardcoded booleans
     * for Raw HTML Anchors mode in schedule campaigns.
     */
    public function up(): void
    {
        Schema::table('schedule_campaigns_articles', function (Blueprint $table) {
            // Store raw rel attribute string (e.g., "nofollow external bookmark sponsored")
            // Takes priority over individual boolean fields when present
            $table->string('raw_rel_attr', 255)->nullable()->after('noreferrer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_campaigns_articles', function (Blueprint $table) {
            $table->dropColumn('raw_rel_attr');
        });
    }
};
