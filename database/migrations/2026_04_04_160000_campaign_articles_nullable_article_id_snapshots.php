<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep campaign_articles + campaign_posts when master Article is hard-deleted:
     * article_id becomes NULL (nullOnDelete) and snapshots hold copy for UI / rebuilds.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('campaign_articles', 'article_title_snapshot')) {
            Schema::table('campaign_articles', function (Blueprint $table) {
                $table->string('article_title_snapshot', 255)->nullable()->after('article_id');
            });
        }

        if (! Schema::hasColumn('campaign_articles', 'article_body_snapshot')) {
            Schema::table('campaign_articles', function (Blueprint $table) {
                $table->longText('article_body_snapshot')->nullable()->after('article_title_snapshot');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        $this->dropArticleForeignIfExists('campaign_articles');

        DB::statement('ALTER TABLE campaign_articles MODIFY article_id BIGINT UNSIGNED NULL');

        Schema::table('campaign_articles', function (Blueprint $table) {
            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->nullOnDelete();
        });

        DB::statement("
            UPDATE campaign_articles ca
            INNER JOIN articles a ON ca.article_id = a.id
            SET
                ca.article_title_snapshot = COALESCE(NULLIF(TRIM(ca.article_title_snapshot), ''), a.name),
                ca.article_body_snapshot = COALESCE(ca.article_body_snapshot, a.description)
            WHERE ca.article_id IS NOT NULL
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
