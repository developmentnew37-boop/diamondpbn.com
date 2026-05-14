<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sidebar_campaign_links', 'sponsored')) {
            Schema::table('sidebar_campaign_links', function (Blueprint $table) {
                $table->boolean('sponsored')->default(false)->after('nofollow');
            });
        }

        if (!Schema::hasColumn('hidden_links_campaigns_links', 'sponsored')) {
            Schema::table('hidden_links_campaigns_links', function (Blueprint $table) {
                $table->boolean('sponsored')->default(false)->after('nofollow');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sidebar_campaign_links', 'sponsored')) {
            Schema::table('sidebar_campaign_links', function (Blueprint $table) {
                $table->dropColumn('sponsored');
            });
        }

        if (Schema::hasColumn('hidden_links_campaigns_links', 'sponsored')) {
            Schema::table('hidden_links_campaigns_links', function (Blueprint $table) {
                $table->dropColumn('sponsored');
            });
        }
    }
};

