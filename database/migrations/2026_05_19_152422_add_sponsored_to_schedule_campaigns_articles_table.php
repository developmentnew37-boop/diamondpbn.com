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
        if (! Schema::hasColumn('schedule_campaigns_articles', 'sponsored')) {
            Schema::table('schedule_campaigns_articles', function (Blueprint $table) {
                $table->boolean('sponsored')->default(false)->after('nofollow');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('schedule_campaigns_articles', 'sponsored')) {
            Schema::table('schedule_campaigns_articles', function (Blueprint $table) {
                $table->dropColumn('sponsored');
            });
        }
    }
};
