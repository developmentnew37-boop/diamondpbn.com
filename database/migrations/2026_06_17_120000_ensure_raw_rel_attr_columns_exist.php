<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure raw_rel_attr exists on post campaign article tables.
     * Safe to run if a prior migration was recorded but the column was never created.
     */
    public function up(): void
    {
        $tables = [
            'campaign_articles',
            'schedule_campaigns_articles',
            'wp_scheduled_campaign_articles',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'raw_rel_attr')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $after = null;
                foreach (['noreferrer', 'noopener', 'ugc', 'sponsored', 'nofollow', 'media'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $after = $column;
                        break;
                    }
                }

                if ($after) {
                    $blueprint->string('raw_rel_attr', 255)->nullable()->after($after);
                } else {
                    $blueprint->string('raw_rel_attr', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'campaign_articles',
            'schedule_campaigns_articles',
            'wp_scheduled_campaign_articles',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'raw_rel_attr')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('raw_rel_attr');
            });
        }
    }
};
