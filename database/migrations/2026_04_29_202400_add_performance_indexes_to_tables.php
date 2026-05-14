<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check and add indexes only if they don't exist
        $this->addIndexIfNotExists('articles', 'idx_articles_admin_lock_deleted', ['admin_id', 'lock_at', 'deleted_at']);
        $this->addIndexIfNotExists('articles', 'idx_articles_language_status', ['article_language_id', 'status']);
        $this->addIndexIfNotExists('articles', 'idx_articles_status_lock', ['status', 'lock_at', 'deleted_at']);

        $this->addIndexIfNotExists('campaigns', 'idx_campaigns_admin_created', ['admin_id', 'created_at']);
        $this->addIndexIfNotExists('campaigns', 'idx_campaigns_admin_sticky', ['admin_id', 'is_sticky_campaign']);

        $this->addIndexIfNotExists('schedule_campaigns', 'idx_schedule_campaigns_composite', ['admin_id', 'is_sticky_campaign', 'created_at']);

        $this->addIndexIfNotExists('schedule_sidebar_campaigns', 'idx_schedule_sidebar_admin_created', ['admin_id', 'created_at']);

        $this->addIndexIfNotExists('sidebar_campaigns', 'idx_sidebar_campaigns_admin_created', ['admin_id', 'created_at']);

        $this->addIndexIfNotExists('hidden_links_campaigns', 'idx_hidden_links_admin_created', ['admin_id', 'created_at']);

        // Skip domains table as it has foreign key constraints
        $this->addIndexIfNotExists('domain_categories', 'idx_domain_categories_admin', ['admin_id']);

        $this->addIndexIfNotExists('article_sets', 'idx_article_sets_admin', ['admin_id']);

        $this->addIndexIfNotExists('domain_sets', 'idx_domain_sets_admin', ['admin_id']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('articles', 'idx_articles_admin_lock_deleted');
        $this->dropIndexIfExists('articles', 'idx_articles_language_status');
        $this->dropIndexIfExists('articles', 'idx_articles_status_lock');

        $this->dropIndexIfExists('campaigns', 'idx_campaigns_admin_created');
        $this->dropIndexIfExists('campaigns', 'idx_campaigns_admin_sticky');

        $this->dropIndexIfExists('schedule_campaigns', 'idx_schedule_campaigns_composite');

        $this->dropIndexIfExists('schedule_sidebar_campaigns', 'idx_schedule_sidebar_admin_created');

        $this->dropIndexIfExists('sidebar_campaigns', 'idx_sidebar_campaigns_admin_created');

        $this->dropIndexIfExists('hidden_links_campaigns', 'idx_hidden_links_admin_created');

        $this->dropIndexIfExists('domain_categories', 'idx_domain_categories_admin');

        $this->dropIndexIfExists('article_sets', 'idx_article_sets_admin');

        $this->dropIndexIfExists('domain_sets', 'idx_domain_sets_admin');
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName, $columns) {
                $blueprint->index($columns, $indexName);
            });
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        }
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
