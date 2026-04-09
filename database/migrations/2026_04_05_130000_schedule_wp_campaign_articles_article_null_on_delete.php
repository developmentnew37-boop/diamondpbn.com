<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hard-deleting an Article must not remove schedule / WP-scheduled campaign article rows
     * (those rows anchor posts and reporting). article_id becomes NULL; snapshots retain copy.
     */
    public function up(): void
    {
        foreach (['schedule_campaigns_articles', 'wp_scheduled_campaign_articles'] as $table) {
            $this->upgradeCampaignArticleLinkTable($table);
        }
    }

    private function upgradeCampaignArticleLinkTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'article_title_snapshot')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('article_title_snapshot', 255)->nullable()->after('article_id');
            });
        }

        if (! Schema::hasColumn($table, 'article_body_snapshot')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->longText('article_body_snapshot')->nullable()->after('article_title_snapshot');
            });
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropArticleForeignIfExists($table);

        DB::statement("ALTER TABLE `{$table}` MODIFY article_id BIGINT UNSIGNED NULL");

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->nullOnDelete();
        });

        DB::statement("
            UPDATE `{$table}` t
            INNER JOIN articles a ON t.article_id = a.id
            SET
                t.article_title_snapshot = COALESCE(NULLIF(TRIM(t.article_title_snapshot), ''), a.name),
                t.article_body_snapshot = COALESCE(t.article_body_snapshot, a.description)
            WHERE t.article_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        //
    }

    private function dropArticleForeignIfExists(string $table): void
    {
        $schema = DB::getDatabaseName();
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = 'article_id'
               AND REFERENCED_TABLE_NAME = 'articles'
             LIMIT 1",
            [$schema, $table]
        );

        $name = $row->name ?? null;
        if ($name) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        }
    }
};
