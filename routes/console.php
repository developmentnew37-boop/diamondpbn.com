<?php

use App\Jobs\PruneDomainStatusChecksJob;
use App\Jobs\PublishScheduledCampaignPostJob;
use App\Jobs\PublishScheduledSidebarBlogrollJob;
use App\Jobs\RefreshWebhookSecretsJob;
use App\Models\Admin\ScheduleCampaignPost;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

$schedulerLockTtlMinutes = 2;
/*
|--------------------------------------------------------------------------
| Scheduled POSTS (already working)
|--------------------------------------------------------------------------
*/

Schedule::call(function () {

    $posts = ScheduleCampaignPost::query()
        ->where('status', 'queued')
        ->where('schedule_at', '<=', now())
        ->where(function ($q) {
            $q->whereNull('locked_at')
                ->orWhere('locked_at', '<', now()->subMinutes(5));
        })
        ->limit(50)
        ->pluck('id');

    foreach ($posts as $id) {
        PublishScheduledCampaignPostJob::dispatch($id)
            ->onQueue('scheduled_campaigns');
    }
})->everyMinute()
    ->name('dispatch_scheduled_campaign_posts')
    ->withoutOverlapping($schedulerLockTtlMinutes)
    ->onOneServer();

// Process Schedule Campaign posts (controller does not dispatch; scheduler dispatches + worker processes)
Schedule::command('queue:work --queue=scheduled_campaigns --sleep=1 --tries=3 --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping($schedulerLockTtlMinutes)
    ->name('work_scheduled_campaigns_queue');

/*
|--------------------------------------------------------------------------
| Scheduled SIDEBAR CAMPAIGNS (NEW)
|--------------------------------------------------------------------------
*/

Schedule::call(function () {

    $taskIds = ScheduleSidebarCampaignTask::query()
        ->where('status', 'queued')
        ->where('schedule_at', '<=', now())
        ->where(function ($q) {
            $q->whereNull('locked_at')
                ->orWhere('locked_at', '<', now()->subMinutes(5));
        })
        ->orderBy('schedule_at')
        ->limit(50)
        ->pluck('id');

    foreach ($taskIds as $id) {
        PublishScheduledSidebarBlogrollJob::dispatch($id)
            ->onQueue('scheduled_sidebar_campaigns');
    }
})
    ->everyMinute()
    ->name('dispatch_scheduled_sidebar_campaign_tasks')
    ->withoutOverlapping($schedulerLockTtlMinutes)
    ->onOneServer();

// Process Scheduled Sidebar campaign tasks
Schedule::command('queue:work --queue=scheduled_sidebar_campaigns --sleep=1 --tries=3 --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping($schedulerLockTtlMinutes)
    ->name('work_scheduled_sidebar_campaigns_queue');

// Schedule::command('queue:work --queue=domainCheck --sleep=1 --tries=3 --stop-when-empty')
//     ->everyMinute()
//     ->withoutOverlapping($schedulerLockTtlMinutes)
//     ->name('work_domain_check_queue');

// Schedule::command('queue:work --queue=campaigns --sleep=1 --tries=3 --stop-when-empty')
//     ->everyMinute()
//     ->withoutOverlapping($schedulerLockTtlMinutes)
//     ->name('work_campaigns_queue');

// Schedule::command('queue:work --queue=sidebar_campaigns --sleep=1 --tries=3 --stop-when-empty')
//     ->everyMinute()
//     ->withoutOverlapping($schedulerLockTtlMinutes)
//     ->name('work_sidebar_campaigns_queue');

// Schedule::command('queue:work --queue=hidden_links_campaigns --sleep=1 --tries=3 --stop-when-empty')
//     ->everyMinute()
//     ->withoutOverlapping($schedulerLockTtlMinutes)
//     ->name('hidden_links_campaigns');

// Schedule campaign: bulk update posts on remote + campaign deletion
// Schedule::command('queue:work --queue=schedule_campaign_bulk_updates --sleep=1 --tries=3 --stop-when-empty')
//     ->everyMinute()
//     ->withoutOverlapping($schedulerLockTtlMinutes)
//     ->name('work_schedule_campaign_bulk_updates');

// Schedule::command('queue:work --queue=schedule_campaign_deletions --sleep=1 --tries=3 --stop-when-empty')
//     ->everyMinute()
//     ->withoutOverlapping($schedulerLockTtlMinutes)
//     ->name('work_schedule_campaign_deletions');

// Schedule Sidebar campaign: bulk update blogroll + campaign deletion
// Schedule::command('queue:work --queue=schedule_sidebar_bulk_updates --sleep=1 --tries=3 --stop-when-empty')
//     ->everyMinute()
//     ->withoutOverlapping($schedulerLockTtlMinutes)
//     ->name('work_schedule_sidebar_bulk_updates');

// Schedule::command('queue:work --queue=schedule_sidebar_deletions --sleep=1 --tries=3 --stop-when-empty')
//     ->everyMinute()
//     ->withoutOverlapping($schedulerLockTtlMinutes)
//     ->name('work_schedule_sidebar_deletions');

Schedule::call(function () {
    Log::info('CRON OK (diamondpbn)');
})->everyMinute();

/*
|--------------------------------------------------------------------------
| WP Scheduled: sync status from WordPress (future → publish, missed schedule)
|--------------------------------------------------------------------------
*/
// Schedule::command('wp-scheduled:sync-status')->hourly()->name('wp_scheduled_sync_status')->onOneServer();

/*
|--------------------------------------------------------------------------
| Webhook secret token rotation (hourly check; interval set in admin UI)
| Dispatches RefreshWebhookSecretsJob → queue: webhook-secret
| Process with Supervisor: queue:work --queue=webhook-secret
|--------------------------------------------------------------------------
*/
Schedule::job(new RefreshWebhookSecretsJob)
    ->hourly()
    ->name('refresh_webhook_secrets')
    ->withoutOverlapping(30)
    ->onOneServer();

// Safety net: clear stale scheduler overlap locks (cache_locks) once daily.
Schedule::command('schedule:clear-cache')
    ->dailyAt('03:05')
    ->name('clear_scheduler_cache_locks')
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Domain status checker: prune old check runs (queued, batched)
|--------------------------------------------------------------------------
*/
Schedule::job(new PruneDomainStatusChecksJob)
    ->dailyAt('03:15')
    ->name('prune_domain_status_checks')
    ->withoutOverlapping(30)
    ->onOneServer();
