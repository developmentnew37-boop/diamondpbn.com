<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\WpScheduledCampaignPost;
use App\Jobs\SyncWpScheduledPostStatusJob;

/**
 * Dispatch status sync jobs for WP scheduled posts that have ?p=ID in remote_url
 * so sync job can replace them with slug permalinks. Run once to fix existing report links.
 */
class ResolveWpScheduledPermalinksCommand extends Command
{
    protected $signature = 'wp-scheduled:resolve-permalinks {--limit=500 : Max posts to queue}';

    protected $description = 'Queue sync jobs for posts with ?p= ID links so remote_url is updated to slug permalink';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $posts = WpScheduledCampaignPost::whereNotNull('remote_id')
            ->whereNotNull('remote_url')
            ->where('remote_url', 'like', '%?p=%')
            ->limit($limit)
            ->pluck('id');

        $count = $posts->count();
        foreach ($posts as $id) {
            SyncWpScheduledPostStatusJob::dispatch($id)->onQueue('wp_scheduled_sync');
        }

        $this->info("Dispatched {$count} sync job(s). Run: php artisan queue:work --queue=wp_scheduled_sync");
        return 0;
    }
}
