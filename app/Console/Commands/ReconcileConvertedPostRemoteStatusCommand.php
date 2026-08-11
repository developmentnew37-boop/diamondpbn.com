<?php

namespace App\Console\Commands;

use App\Jobs\SyncConvertedCampaignRemoteStatusJob;
use App\Models\Admin\ScheduleCampaignPost;
use Illuminate\Console\Command;

class ReconcileConvertedPostRemoteStatusCommand extends Command
{
    protected $signature = 'convert:reconcile-remote-status {--campaign= : Schedule campaign id}';

    protected $description = 'Queue WordPress status sync for converted dripfeed posts (also runs automatically on a schedule)';

    public function handle(): int
    {
        $query = ScheduleCampaignPost::query()
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
            });

        if ($this->option('campaign')) {
            $query->where('schedule_campaign_id', (int) $this->option('campaign'));
        }

        $campaignIds = $query->distinct()->pluck('schedule_campaign_id');

        foreach ($campaignIds as $id) {
            SyncConvertedCampaignRemoteStatusJob::dispatch((int) $id)->onQueue(SyncConvertedCampaignRemoteStatusJob::QUEUE);
        }

        $this->info('Queued remote sync for '.$campaignIds->count().' converted campaign(s).');

        return self::SUCCESS;
    }
}
