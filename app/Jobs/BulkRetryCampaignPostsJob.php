<?php

namespace App\Jobs;

use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkRetryCampaignPostsJob implements ShouldQueue
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
        $this->onQueue('bulk_retry');
    }

    public static function retryableQuery(int $campaignId, bool $includeStuck = false): Builder
    {
        $query = CampaignPost::query()->where('campaign_id', $campaignId);

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
            $campaign = Campaign::find($campaignId);

            if (! $campaign) {
                Log::warning('BulkRetryCampaignPostsJob: Campaign not found', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            if (in_array($campaign->status ?? '', ['paused', 'cancelled'], true)) {
                Log::info('BulkRetryCampaignPostsJob: Skipping paused/cancelled campaign', ['campaign_id' => $campaignId]);
                $skipped++;

                continue;
            }

            $postIds = self::retryableQuery((int) $campaignId, $this->includeStuck)->pluck('id')->all();
            $totalRetried += $this->resetAndDispatch($postIds);
        }

        Log::info('BulkRetryCampaignPostsJob completed', [
            'campaigns_processed' => count($this->campaignIds),
            'posts_retried' => $totalRetried,
            'campaigns_skipped' => $skipped,
            'include_stuck' => $this->includeStuck,
        ]);
    }

    /**
     * @param  list<int>  $postIds
     */
    private function resetAndDispatch(array $postIds): int
    {
        if ($postIds === []) {
            return 0;
        }

        $dispatched = 0;

        foreach (array_chunk($postIds, self::CHUNK_SIZE) as $chunk) {
            CampaignPost::query()
                ->whereIn('id', $chunk)
                ->update([
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'next_retry_at' => null,
                    'locked_at' => null,
                    'lock_token' => null,
                    'locked_until' => null,
                    'last_error' => null,
                    'last_failure_code' => null,
                    'dispatch_generation' => DB::raw('dispatch_generation + 1'),
                    'updated_at' => now(),
                ]);

            $posts = CampaignPost::query()
                ->whereIn('id', $chunk)
                ->get(['id', 'dispatch_generation']);

            foreach ($posts as $post) {
                PublishCampaignPostJob::dispatch((int) $post->id, (int) ($post->dispatch_generation ?? 0))
                    ->onQueue('campaigns');
                $dispatched++;
            }
        }

        return $dispatched;
    }
}
