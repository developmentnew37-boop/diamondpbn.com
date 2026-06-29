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
        if (! Schema::hasColumn('sidebar_campaign_links', 'ugc')) {
            Schema::table('sidebar_campaign_links', function (Blueprint $table) {
                $table->boolean('ugc')->default(false)->after('sponsored');
            });
        }
        if (! Schema::hasColumn('sidebar_campaign_links', 'noopener')) {
            Schema::table('sidebar_campaign_links', function (Blueprint $table) {
                $table->boolean('noopener')->default(false)->after('ugc');
            });
        }
        if (! Schema::hasColumn('sidebar_campaign_links', 'noreferrer')) {
            Schema::table('sidebar_campaign_links', function (Blueprint $table) {
                $table->boolean('noreferrer')->default(false)->after('noopener');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sidebar_campaign_links', function (Blueprint $table) {
            if (Schema::hasColumn('sidebar_campaign_links', 'ugc')) {
                $table->dropColumn('ugc');
            }
            if (Schema::hasColumn('sidebar_campaign_links', 'noopener')) {
                $table->dropColumn('noopener');
            }
            if (Schema::hasColumn('sidebar_campaign_links', 'noreferrer')) {
                $table->dropColumn('noreferrer');
            }
        });
    }
};
