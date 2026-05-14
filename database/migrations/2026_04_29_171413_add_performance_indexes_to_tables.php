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
        // Helper function to check if index exists
        $indexExists = function ($table, $indexName) {
            try {
                $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
                return !empty($indexes);
            } catch (\Exception $e) {
                return false;
            }
        };

        // Campaigns table indexes
        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('campaigns', 'campaigns_admin_id_index')) {
                    $table->index('admin_id');
                }
                if (!$indexExists('campaigns', 'campaigns_created_at_index')) {
                    $table->index('created_at');
                }
                if (!$indexExists('campaigns', 'campaigns_admin_id_created_at_index')) {
                    $table->index(['admin_id', 'created_at']);
                }
                if (!$indexExists('campaigns', 'campaigns_admin_id_is_sticky_campaign_index')) {
                    $table->index(['admin_id', 'is_sticky_campaign']);
                }
            });
        }

        // Schedule campaigns table indexes
        if (Schema::hasTable('schedule_campaigns')) {
            Schema::table('schedule_campaigns', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('schedule_campaigns', 'schedule_campaigns_admin_id_index')) {
                    $table->index('admin_id');
                }
                if (!$indexExists('schedule_campaigns', 'schedule_campaigns_created_at_index')) {
                    $table->index('created_at');
                }
                if (!$indexExists('schedule_campaigns', 'schedule_campaigns_admin_id_created_at_index')) {
                    $table->index(['admin_id', 'created_at']);
                }
                if (!$indexExists('schedule_campaigns', 'schedule_campaigns_admin_id_is_sticky_campaign_index')) {
                    $table->index(['admin_id', 'is_sticky_campaign']);
                }
            });
        }

        // Schedule sidebar campaigns table indexes
        if (Schema::hasTable('schedule_sidebar_campaigns')) {
            Schema::table('schedule_sidebar_campaigns', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('schedule_sidebar_campaigns', 'schedule_sidebar_campaigns_admin_id_index')) {
                    $table->index('admin_id');
                }
                if (!$indexExists('schedule_sidebar_campaigns', 'schedule_sidebar_campaigns_created_at_index')) {
                    $table->index('created_at');
                }
                if (!$indexExists('schedule_sidebar_campaigns', 'schedule_sidebar_campaigns_admin_id_created_at_index')) {
                    $table->index(['admin_id', 'created_at']);
                }
            });
        }

        // Sidebar campaigns table indexes
        if (Schema::hasTable('sidebar_campaigns')) {
            Schema::table('sidebar_campaigns', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('sidebar_campaigns', 'sidebar_campaigns_admin_id_index')) {
                    $table->index('admin_id');
                }
                if (!$indexExists('sidebar_campaigns', 'sidebar_campaigns_created_at_index')) {
                    $table->index('created_at');
                }
                if (!$indexExists('sidebar_campaigns', 'sidebar_campaigns_admin_id_created_at_index')) {
                    $table->index(['admin_id', 'created_at']);
                }
            });
        }

        // Hidden links campaigns table indexes
        if (Schema::hasTable('hidden_links_campaigns')) {
            Schema::table('hidden_links_campaigns', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('hidden_links_campaigns', 'hidden_links_campaigns_admin_id_index')) {
                    $table->index('admin_id');
                }
                if (!$indexExists('hidden_links_campaigns', 'hidden_links_campaigns_created_at_index')) {
                    $table->index('created_at');
                }
                if (!$indexExists('hidden_links_campaigns', 'hidden_links_campaigns_admin_id_created_at_index')) {
                    $table->index(['admin_id', 'created_at']);
                }
            });
        }

        // Domains table indexes
        if (Schema::hasTable('domains')) {
            Schema::table('domains', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('domains', 'domains_admin_id_index')) {
                    $table->index('admin_id');
                }
                if (!$indexExists('domains', 'domains_domain_category_id_index')) {
                    $table->index('domain_category_id');
                }
            });
        }

        // Articles table indexes
        if (Schema::hasTable('articles')) {
            Schema::table('articles', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('articles', 'articles_admin_id_index')) {
                    $table->index('admin_id');
                }
                if (!$indexExists('articles', 'articles_lock_at_index')) {
                    $table->index('lock_at');
                }
                if (!$indexExists('articles', 'articles_deleted_at_index')) {
                    $table->index('deleted_at');
                }
                if (!$indexExists('articles', 'articles_status_index')) {
                    $table->index('status');
                }
                if (!$indexExists('articles', 'articles_admin_id_lock_at_deleted_at_index')) {
                    $table->index(['admin_id', 'lock_at', 'deleted_at']);
                }
            });
        }

        // Domain categories table indexes
        if (Schema::hasTable('domain_categories')) {
            Schema::table('domain_categories', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('domain_categories', 'domain_categories_admin_id_index')) {
                    $table->index('admin_id');
                }
            });
        }

        // Campaign posts table indexes
        if (Schema::hasTable('campaign_posts')) {
            Schema::table('campaign_posts', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('campaign_posts', 'campaign_posts_campaign_id_index')) {
                    $table->index('campaign_id');
                }
                if (!$indexExists('campaign_posts', 'campaign_posts_status_index')) {
                    $table->index('status');
                }
            });
        }

        // Schedule campaign posts table indexes (only if table exists)
        if (Schema::hasTable('schedule_campaign_posts')) {
            Schema::table('schedule_campaign_posts', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('schedule_campaign_posts', 'schedule_campaign_posts_schedule_campaign_id_index')) {
                    $table->index('schedule_campaign_id');
                }
                if (!$indexExists('schedule_campaign_posts', 'schedule_campaign_posts_status_index')) {
                    $table->index('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Helper function to check if index exists
        $indexExists = function ($table, $indexName) {
            try {
                $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
                return !empty($indexes);
            } catch (\Exception $e) {
                return false;
            }
        };

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('campaigns', 'campaigns_admin_id_index')) {
                    $table->dropIndex(['admin_id']);
                }
                if ($indexExists('campaigns', 'campaigns_created_at_index')) {
                    $table->dropIndex(['created_at']);
                }
                if ($indexExists('campaigns', 'campaigns_admin_id_created_at_index')) {
                    $table->dropIndex(['admin_id', 'created_at']);
                }
                if ($indexExists('campaigns', 'campaigns_admin_id_is_sticky_campaign_index')) {
                    $table->dropIndex(['admin_id', 'is_sticky_campaign']);
                }
            });
        }

        if (Schema::hasTable('schedule_campaigns')) {
            Schema::table('schedule_campaigns', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('schedule_campaigns', 'schedule_campaigns_admin_id_index')) {
                    $table->dropIndex(['admin_id']);
                }
                if ($indexExists('schedule_campaigns', 'schedule_campaigns_created_at_index')) {
                    $table->dropIndex(['created_at']);
                }
                if ($indexExists('schedule_campaigns', 'schedule_campaigns_admin_id_created_at_index')) {
                    $table->dropIndex(['admin_id', 'created_at']);
                }
                if ($indexExists('schedule_campaigns', 'schedule_campaigns_admin_id_is_sticky_campaign_index')) {
                    $table->dropIndex(['admin_id', 'is_sticky_campaign']);
                }
            });
        }

        if (Schema::hasTable('schedule_sidebar_campaigns')) {
            Schema::table('schedule_sidebar_campaigns', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('schedule_sidebar_campaigns', 'schedule_sidebar_campaigns_admin_id_index')) {
                    $table->dropIndex(['admin_id']);
                }
                if ($indexExists('schedule_sidebar_campaigns', 'schedule_sidebar_campaigns_created_at_index')) {
                    $table->dropIndex(['created_at']);
                }
                if ($indexExists('schedule_sidebar_campaigns', 'schedule_sidebar_campaigns_admin_id_created_at_index')) {
                    $table->dropIndex(['admin_id', 'created_at']);
                }
            });
        }

        if (Schema::hasTable('sidebar_campaigns')) {
            Schema::table('sidebar_campaigns', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('sidebar_campaigns', 'sidebar_campaigns_admin_id_index')) {
                    $table->dropIndex(['admin_id']);
                }
                if ($indexExists('sidebar_campaigns', 'sidebar_campaigns_created_at_index')) {
                    $table->dropIndex(['created_at']);
                }
                if ($indexExists('sidebar_campaigns', 'sidebar_campaigns_admin_id_created_at_index')) {
                    $table->dropIndex(['admin_id', 'created_at']);
                }
            });
        }

        if (Schema::hasTable('hidden_links_campaigns')) {
            Schema::table('hidden_links_campaigns', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('hidden_links_campaigns', 'hidden_links_campaigns_admin_id_index')) {
                    $table->dropIndex(['admin_id']);
                }
                if ($indexExists('hidden_links_campaigns', 'hidden_links_campaigns_created_at_index')) {
                    $table->dropIndex(['created_at']);
                }
                if ($indexExists('hidden_links_campaigns', 'hidden_links_campaigns_admin_id_created_at_index')) {
                    $table->dropIndex(['admin_id', 'created_at']);
                }
            });
        }

        if (Schema::hasTable('domains')) {
            Schema::table('domains', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('domains', 'domains_admin_id_index')) {
                    $table->dropIndex(['admin_id']);
                }
                if ($indexExists('domains', 'domains_domain_category_id_index')) {
                    $table->dropIndex(['domain_category_id']);
                }
            });
        }

        if (Schema::hasTable('articles')) {
            Schema::table('articles', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('articles', 'articles_admin_id_index')) {
                    $table->dropIndex(['admin_id']);
                }
                if ($indexExists('articles', 'articles_lock_at_index')) {
                    $table->dropIndex(['lock_at']);
                }
                if ($indexExists('articles', 'articles_deleted_at_index')) {
                    $table->dropIndex(['deleted_at']);
                }
                if ($indexExists('articles', 'articles_status_index')) {
                    $table->dropIndex(['status']);
                }
                if ($indexExists('articles', 'articles_admin_id_lock_at_deleted_at_index')) {
                    $table->dropIndex(['admin_id', 'lock_at', 'deleted_at']);
                }
            });
        }

        if (Schema::hasTable('domain_categories')) {
            Schema::table('domain_categories', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('domain_categories', 'domain_categories_admin_id_index')) {
                    $table->dropIndex(['admin_id']);
                }
            });
        }

        if (Schema::hasTable('campaign_posts')) {
            Schema::table('campaign_posts', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('campaign_posts', 'campaign_posts_campaign_id_index')) {
                    $table->dropIndex(['campaign_id']);
                }
                if ($indexExists('campaign_posts', 'campaign_posts_status_index')) {
                    $table->dropIndex(['status']);
                }
            });
        }

        if (Schema::hasTable('schedule_campaign_posts')) {
            Schema::table('schedule_campaign_posts', function (Blueprint $table) use ($indexExists) {
                if ($indexExists('schedule_campaign_posts', 'schedule_campaign_posts_schedule_campaign_id_index')) {
                    $table->dropIndex(['schedule_campaign_id']);
                }
                if ($indexExists('schedule_campaign_posts', 'schedule_campaign_posts_status_index')) {
                    $table->dropIndex(['status']);
                }
            });
        }
    }
};
