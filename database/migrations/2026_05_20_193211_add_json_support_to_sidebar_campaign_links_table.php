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
        Schema::table('sidebar_campaign_links', function (Blueprint $table) {
            $table->string('target_url_type', 10)->default('single')->after('target_url');
            $table->string('anchor_keyword_type', 10)->default('single')->after('anchor_keyword');
        });

        Schema::table('schedule_sidebar_campaign_links', function (Blueprint $table) {
            $table->string('target_url_type', 10)->default('single')->after('target_url');
            $table->string('anchor_keyword_type', 10)->default('single')->after('anchor_keyword');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sidebar_campaign_links', function (Blueprint $table) {
            $table->dropColumn(['target_url_type', 'anchor_keyword_type']);
        });

        Schema::table('schedule_sidebar_campaign_links', function (Blueprint $table) {
            $table->dropColumn(['target_url_type', 'anchor_keyword_type']);
        });
    }
};
