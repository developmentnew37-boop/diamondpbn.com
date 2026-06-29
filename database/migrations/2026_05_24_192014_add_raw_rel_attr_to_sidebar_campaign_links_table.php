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
            $table->string('raw_rel_attr', 255)->nullable()->after('noreferrer');
        });

        Schema::table('schedule_sidebar_campaign_links', function (Blueprint $table) {
            $table->string('raw_rel_attr', 255)->nullable()->after('noreferrer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sidebar_campaign_links', function (Blueprint $table) {
            $table->dropColumn('raw_rel_attr');
        });

        Schema::table('schedule_sidebar_campaign_links', function (Blueprint $table) {
            $table->dropColumn('raw_rel_attr');
        });
    }
};
