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
        if (! Schema::hasColumn('schedule_campaigns_articles', 'ugc')) {
            Schema::table('schedule_campaigns_articles', function (Blueprint $table) {
                $table->boolean('ugc')->default(false)->after('sponsored');
            });
        }
        if (! Schema::hasColumn('schedule_campaigns_articles', 'noopener')) {
            Schema::table('schedule_campaigns_articles', function (Blueprint $table) {
                $table->boolean('noopener')->default(false)->after('ugc');
            });
        }
        if (! Schema::hasColumn('schedule_campaigns_articles', 'noreferrer')) {
            Schema::table('schedule_campaigns_articles', function (Blueprint $table) {
                $table->boolean('noreferrer')->default(false)->after('noopener');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_campaigns_articles', function (Blueprint $table) {
            if (Schema::hasColumn('schedule_campaigns_articles', 'ugc')) {
                $table->dropColumn('ugc');
            }
            if (Schema::hasColumn('schedule_campaigns_articles', 'noopener')) {
                $table->dropColumn('noopener');
            }
            if (Schema::hasColumn('schedule_campaigns_articles', 'noreferrer')) {
                $table->dropColumn('noreferrer');
            }
        });
    }
};
