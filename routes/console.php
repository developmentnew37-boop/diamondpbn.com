<?php

use App\Jobs\ApplyConvertedPostScheduleJob;
use App\Jobs\ApplyConvertedSidebarScheduleJob;
use App\Jobs\PruneDomainStatusChecksJob;
use App\Jobs\PublishScheduledCampaignPostJob;
use App\Jobs\PublishScheduledSidebarBlogrollJob;
use App\Jobs\RefreshWebhookSecretsJob;
use App\Jobs\SyncConvertedCampaignRemoteStatusJob;
use App\Jobs\SyncConvertedSidebarCampaignRemoteStatusJob;
use App\Models\Admin\ScheduleCampaignPost;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

$schedulerLockTtlMinutes = 2;

Schedule::command('domains:health-check')
    ->everyFifteenMinutes()
    ->name('domains_health_check')
    ->withoutOverlapping(15)
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Scheduled POSTS (already working)
|--------------------------------------------------------------------------
*/

Schedule::call(function () {

    $posts = ScheduleCampaignPost::query()
        ->where('status', 'queued')
        ->where(function ($q) {
            $q->where('is_converted_live', false)->orWhereNull('is_converted_live');
        })
        ->where('schedule_at', '<=', now())
        ->where(function ($q) {
            $q->whereNull('locked_at')
                ->orWhere('locked_at', '<', now()->subMinutes(5));
        })
        ->limit(50)
        ->get(['id', 'dispatch_generation']);

    foreach ($posts as $post) {
        PublishScheduledCampaignPostJob::dispatch($post->id, (int) ($post->dispatch_generation ?? 0))
            ->onQueue('scheduled_campaigns');
    }
})->everyMinute()
    ->name('dispatch_scheduled_campaign_posts')
    ->withoutOverlapping($schedulerLockTtlMinutes)
    ->onOneServer();

Schedule::call(function () {
    $posts = ScheduleCampaignPost::query()
        ->where('is_converted_live', true)
        ->whereIn('conversion_phase', ['drafted', 'scheduled_publish'])
        ->whereDate('schedule_at', '<=', now()->toDateString())
        ->where(function ($q) {
            $q->whereNull('locked_at')
                ->orWhere('locked_at', '<', now()->subMinutes(5));
        })
        ->whereNotIn('status', ['success'])
        ->limit(50)
        ->pluck('id');

    foreach ($posts as $id) {
        ApplyConvertedPostScheduleJob::dispatch($id)->onQueue(ApplyConvertedPostScheduleJob::QUEUE);
    }
})->everyMinute()
    ->name('dispatch_converted_live_schedule_posts')
    ->withoutOverlapping($schedulerLockTtlMinutes)
    ->onOneServer();

Schedule::call(function () {
    $campaignIds = ScheduleCampaignPost::query()
        ->where('is_converted_live', true)
        ->whereNotNull('remote_id')
        ->where(function ($q) {
            $q->whereNull('remote_status')
                ->orWhere('remote_status', '')
                ->orWhere(function ($q2) {
                    $q2->where('status', 'success')
                        ->where(function ($q3) {
                            $q3->whereNull('remote_status')
                                ->orWhereNotIn('remote_status', ['publish', 'future', 'draft']);
                        });
                });
        })
        ->distinct()
        ->orderBy('schedule_campaign_id')
        ->limit(30)
        ->pluck('schedule_campaign_id');

    foreach ($campaignIds as $id) {
        SyncConvertedCampaignRemoteStatusJob::dispatch((int) $id)->onQueue(SyncConvertedCampaignRemoteStatusJob::QUEUE);
    }
})->everyFiveMinutes()
    ->name('sync_converted_dripfeed_remote_status')
    ->withoutOverlapping(5)
    ->onOneServer();

// campaign_conversions: handled by Supervisor scheduled-campaign-worker (persistent).

Schedule::call(function () {
    $today = now()->toDateString();
    $tasks = ScheduleSidebarCampaignTask::query()
        ->where('is_converted_live', true)
        ->whereIn('conversion_phase', ['drafted', 'scheduled_publish'])
        ->where(function ($q) use ($today) {
            $q->where(function ($q2) use ($today) {
                $q2->whereNotNull('original_schedule_at')
                    ->whereDate('original_schedule_at', '<=', $today);
            })->orWhere(function ($q2) use ($today) {
                $q2->whereNull('original_schedule_at')
                    ->whereDate('schedule_at', '<=', $today);
            });
        })
        ->where(function ($q) {
            $q->whereNull('locked_at')
                ->orWhere('locked_at', '<', now()->subMinutes(5));
        })
        ->whereNotIn('status', ['success'])
        ->limit(50)
        ->pluck('id');

    foreach ($tasks as $id) {
        ApplyConvertedSidebarScheduleJob::dispatch($id)->onQueue(ApplyConvertedSidebarScheduleJob::QUEUE);
    }
})->everyMinute()
    ->name('dispatch_converted_live_schedule_sidebar_tasks')
    ->withoutOverlapping($schedulerLockTtlMinutes)
    ->onOneServer();

Schedule::call(function () {
    $campaignIds = ScheduleSidebarCampaignTask::query()
        ->where('is_converted_live', true)
        ->whereNotNull('remote_id')
        ->where(function ($q) {
            $q->whereNull('remote_status')
                ->orWhere('remote_status', '')
                ->orWhere(function ($q2) {
                    $q2->where('status', 'success')
                        ->where(function ($q3) {
                            $q3->whereNull('remote_status')
                                ->orWhereNotIn('remote_status', ['publish', 'draft']);
                        });
                });
        })
        ->distinct()
        ->orderBy('schedule_sidebar_campaign_id')
        ->limit(30)
        ->pluck('schedule_sidebar_campaign_id');

    foreach ($campaignIds as $id) {
        SyncConvertedSidebarCampaignRemoteStatusJob::dispatch((int) $id)->onQueue(SyncConvertedSidebarCampaignRemoteStatusJob::QUEUE);
    }
})->everyFiveMinutes()
    ->name('sync_converted_sidebar_dripfeed_remote_status')
    ->withoutOverlapping(5)
    ->onOneServer();

// sidebar_campaign_conversions: handled by Supervisor scheduled-sidebar-worker (persistent).

// scheduled_campaigns: handled by Supervisor scheduled-campaign-worker (persistent).

/*
|--------------------------------------------------------------------------
| Scheduled SIDEBAR CAMPAIGNS (NEW)
|--------------------------------------------------------------------------
*/

Schedule::call(function () {

    $taskIds = ScheduleSidebarCampaignTask::query()
        ->where('status', 'queued')
        ->where(function ($q) {
            $q->where('is_converted_live', false)->orWhereNull('is_converted_live');
        })
        ->where('schedule_at', '<=', now())
        ->where(function ($q) {
            $q->whereNull('locked_at')
                ->orWhere('locked_at', '<', now()->subMinutes(5));
        })
        ->orderBy('schedule_at')
        ->limit(50)
        ->get(['id', 'dispatch_generation']);

    foreach ($taskIds as $task) {
        PublishScheduledSidebarBlogrollJob::dispatch($task->id, (int) ($task->dispatch_generation ?? 0))
            ->onQueue('scheduled_sidebar_campaigns');
    }
})
    ->everyMinute()
    ->name('dispatch_scheduled_sidebar_campaign_tasks')
    ->withoutOverlapping($schedulerLockTtlMinutes)
    ->onOneServer();

// scheduled_sidebar_campaigns: handled by Supervisor scheduled-sidebar-worker (persistent).

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
