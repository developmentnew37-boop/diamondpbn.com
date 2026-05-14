<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Admin\Article;
use App\Models\Admin\Campaign;
use App\Models\Admin\Domain;
use App\Observers\ArticleObserver;
use App\Observers\CampaignObserver;
use App\Observers\DomainObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers for cache invalidation
        Article::observe(ArticleObserver::class);
        Campaign::observe(CampaignObserver::class);
        Domain::observe(DomainObserver::class);
    }
}
