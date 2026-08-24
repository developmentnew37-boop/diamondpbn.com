<?php

namespace App\Console\Commands;

use App\Models\Admin\ScheduleCampaign;
use App\Services\ScheduleCampaignTargetCounterService;
use Illuminate\Console\Command;

class SyncScheduleCampaignCountersCommand extends Command
{
    protected $signature = 'schedule-campaigns:sync-counters
                            {--only-drifted : Only sync campaigns where completed+failed exceeds total}
                            {--id= : Sync a single schedule campaign id}';

    protected $description = 'Recount schedule campaign counters from post rows and fix status';

    public function handle(ScheduleCampaignTargetCounterService $counters): int
    {
        $query = ScheduleCampaign::query()->orderBy('id');

        if ($id = $this->option('id')) {
            $query->whereKey((int) $id);
        } elseif ($this->option('only-drifted')) {
            $query->whereRaw('(completed_targets + failed_targets) > total_targets');
        }

        $synced = 0;
        $query->chunkById(100, function ($campaigns) use ($counters, &$synced) {
            foreach ($campaigns as $campaign) {
                $before = [
                    'completed' => (int) $campaign->completed_targets,
                    'failed' => (int) $campaign->failed_targets,
                    'status' => (string) $campaign->status,
                ];

                $fresh = $counters->syncCampaignFromPosts($campaign);

                $changed = $before['completed'] !== (int) $fresh->completed_targets
                    || $before['failed'] !== (int) $fresh->failed_targets
                    || $before['status'] !== (string) $fresh->status;

                if ($changed) {
                    $this->line(sprintf(
                        '#%d %s: %d/%d/%s → %d/%d/%s',
                        $fresh->id,
                        $fresh->campaign_no,
                        $before['completed'],
                        $before['failed'],
                        $before['status'],
                        (int) $fresh->completed_targets,
                        (int) $fresh->failed_targets,
                        (string) $fresh->status,
                    ));
                }

                $synced++;
            }
        });

        $this->info("Synced {$synced} schedule campaign(s).");

        return self::SUCCESS;
    }
}
