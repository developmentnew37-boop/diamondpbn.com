<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\PublishScheduledCampaignPostJob;
use App\Models\Admin\ScheduleCampaignPost;
use App\Jobs\PublishScheduledSidebarBlogrollJob;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use Illuminate\Support\Facades\Log;
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
    ->withoutOverlapping()
    ->onOneServer();


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
    ->onOneServer();


Schedule::command('queue:work --queue=domainCheck --sleep=1 --tries=3 --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('work_domain_check_queue');

Schedule::command('queue:work --queue=campaigns --sleep=1 --tries=3 --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('work_campaigns_queue');

Schedule::command('queue:work --queue=sidebar_campaigns --sleep=1 --tries=3 --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('work_sidebar_campaigns_queue');

Schedule::command('queue:work --queue=hidden_links_campaigns --sleep=1 --tries=3 --stop-when-empty')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('hidden_links_campaigns');


Schedule::call(function () {
    Log::info('CRON OK (diamondpbn)');
})->everyMinute();
