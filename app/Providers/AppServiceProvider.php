<?php

namespace App\Providers;

use App\Models\Admin\Article;
use App\Models\Admin\Campaign;
use App\Models\Admin\Domain;
use App\Observers\ArticleObserver;
use App\Observers\BillableCampaignBillingObserver;
use App\Observers\CampaignObserver;
use App\Observers\DomainObserver;
use App\Support\BillableCampaignRegistry;
use Illuminate\Support\ServiceProvider;

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

        $billingObserver = BillableCampaignBillingObserver::class;
        $registered = [];
        foreach (BillableCampaignRegistry::TYPES as $entry) {
            $class = $entry['class'];
            if (isset($registered[$class])) {
                continue;
            }
            $registered[$class] = true;
            $class::observe($billingObserver);
        }
    }
}
