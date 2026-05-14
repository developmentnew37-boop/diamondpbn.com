<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('campaign_articles', 'sponsored')) {
            Schema::table('campaign_articles', function (Blueprint $table) {
                $table->boolean('sponsored')->default(false)->after('nofollow');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('campaign_articles', 'sponsored')) {
            Schema::table('campaign_articles', function (Blueprint $table) {
                $table->dropColumn('sponsored');
            });
        }
    }
};

