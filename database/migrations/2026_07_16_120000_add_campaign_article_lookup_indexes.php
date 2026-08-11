<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campaign picker: unused + unlocked + language, newest first
        $this->addIndexIfNotExists(
            'articles',
            'idx_articles_campaign_language_lookup',
            ['status', 'article_language_id', 'lock_at', 'deleted_at', 'created_at']
        );

        // Campaign search/filter by category
        $this->addIndexIfNotExists(
            'articles',
            'idx_articles_campaign_category_lookup',
            ['status', 'article_category_id', 'lock_at', 'deleted_at', 'created_at']
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('articles', 'idx_articles_campaign_language_lookup');
        $this->dropIndexIfExists('articles', 'idx_articles_campaign_category_lookup');
    }

    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName, $columns) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$indexName]);

        return count($indexes) > 0;
    }
};
