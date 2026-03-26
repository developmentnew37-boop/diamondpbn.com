<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\WpScheduledCampaignPost;
use App\Jobs\SyncWpScheduledPostStatusJob;

/**
 * Dispatch status sync jobs for WP scheduled posts that are still "future" on WordPress.
 * Schedule in app/Console/Kernel: $schedule->command('wp-scheduled:sync-status')->hourly();
 */
class SyncWpScheduledStatusCommand extends Command
{
    protected $signature = 'wp-scheduled:sync-status';

    protected $description = 'Sync WordPress scheduled post statuses (future → publish)';

    public function handle(): int
    {
        $posts = WpScheduledCampaignPost::whereNotNull('remote_id')
            ->where(function ($q) {
                $q->where('remote_status', 'future')->orWhereNull('remote_status');
            })
            ->limit(100)
            ->pluck('id');

        foreach ($posts as $id) {
            SyncWpScheduledPostStatusJob::dispatch($id)->onQueue('wp_scheduled_sync');
        }

        $this->info('Dispatched ' . $posts->count() . ' sync jobs.');
        return 0;
    }
}
