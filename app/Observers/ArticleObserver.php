<?php

namespace App\Observers;

use App\Models\Admin\Article;
use Illuminate\Support\Facades\Cache;

class ArticleObserver
{
    /**
     * Handle the Article "created" event.
     */
    public function created(Article $article): void
    {
        $this->clearCaches($article->admin_id);
    }

    /**
     * Handle the Article "updated" event.
     */
    public function updated(Article $article): void
    {
        $this->clearCaches($article->admin_id);
    }

    /**
     * Handle the Article "deleted" event.
     */
    public function deleted(Article $article): void
    {
        $this->clearCaches($article->admin_id);
    }

    /**
     * Clear relevant caches
     */
    private function clearCaches(int $adminId): void
    {
        Cache::forget('dashboard_data_' . $adminId . '_user');
        Cache::forget('dashboard_data_' . $adminId . '_super');
        Cache::forget('campaign_create_data_' . $adminId);
    }
}
