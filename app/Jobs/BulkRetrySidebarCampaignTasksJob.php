<?php

namespace App\Jobs;

use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkRetrySidebarCampaignTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    private const CHUNK_SIZE = 200;

    /**
     * @param  int[]  $campaignIds
     */
    public function __construct(
        public array $campaignIds,
        public bool $includeStuck = false,
    ) {
        $this->onQueue('bulk_retry_sidebar_campaigns');
    }

    public static function retryableQuery(int $campaignId, bool $includeStuck = false): Builder
    {
        $query = SidebarCampaignTask::query()->where('sidebar_campaign_id', $campaignId);

        if ($includeStuck) {
            return $query->whereIn('status', ['queued', 'failed', 'publishing']);
        }

        return $query->where('status', 'failed');
    }

    public function handle(): void
    {
        $totalRetried = 0;
        $skipped = 0;

        foreach ($this->campaignIds as $campaignId) {
            $campaign = SidebarCampaign::find($campaignId);

            if (! $campaign) {
                Log::warning('BulkRetrySidebarCampaignTasksJob: Campaign not found', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
                Log::info('BulkRetrySidebarCampaignTasksJob: Skipping paused/cancelled campaign', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            $taskIds = self::retryableQuery((int) $campaignId, $this->includeStuck)->pluck('id')->all();
            $totalRetried += $this->resetAndDispatch($taskIds);
        }

        Log::info('BulkRetrySidebarCampaignTasksJob completed', [
            'campaigns_processed' => count($this->campaignIds),
            'tasks_retried' => $totalRetried,
            'campaigns_skipped' => $skipped,
            'include_stuck' => $this->includeStuck,
        ]);
    }

    /**
     * @param  list<int>  $taskIds
     */
    private function resetAndDispatch(array $taskIds): int
    {
        if ($taskIds === []) {
            return 0;
        }

        $dispatched = 0;

        foreach (array_chunk($taskIds, self::CHUNK_SIZE) as $chunk) {
            SidebarCampaignTask::query()
                ->whereIn('id', $chunk)
                ->update([
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'lock_token' => null,
                    'last_error' => null,
                    'dispatch_generation' => DB::raw('dispatch_generation + 1'),
                    'updated_at' => now(),
                ]);

            $tasks = SidebarCampaignTask::query()
                ->whereIn('id', $chunk)
                ->get(['id', 'dispatch_generation']);

            foreach ($tasks as $task) {
                PublishSidebarBlogrollJob::dispatch((int) $task->id, (int) ($task->dispatch_generation ?? 0))
                    ->onQueue('sidebar_campaigns');
                $dispatched++;
            }
        }

        return $dispatched;
    }
}
