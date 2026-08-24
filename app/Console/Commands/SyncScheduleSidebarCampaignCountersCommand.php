<?php

namespace App\Console\Commands;

use App\Models\Admin\ScheduleSidebarCampaign;
use App\Services\ScheduleSidebarCampaignTargetCounterService;
use Illuminate\Console\Command;

class SyncScheduleSidebarCampaignCountersCommand extends Command
{
    protected $signature = 'schedule-sidebar-campaigns:sync-counters
                            {--only-drifted : Only sync campaigns where completed+failed exceeds total}
                            {--id= : Sync a single schedule sidebar campaign id}';

    protected $description = 'Recount schedule sidebar campaign counters from task rows and fix status';

    public function handle(ScheduleSidebarCampaignTargetCounterService $counters): int
    {
        $query = ScheduleSidebarCampaign::query()->orderBy('id');

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

                $fresh = $counters->syncCampaignFromTasks($campaign);

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

        $this->info("Synced {$synced} schedule sidebar campaign(s).");

        return self::SUCCESS;
    }
}
