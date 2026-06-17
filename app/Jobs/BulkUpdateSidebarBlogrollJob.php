<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\SidebarCampaignTask;
use App\Services\BlogrollApiService;

/**
 * Update sidebar blogroll links on remote (one batch). Runs in background via queue.
 * Each item: ['task_id' => int, 'keyword' => string, 'link' => string]
 */
class BulkUpdateSidebarBlogrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 min per batch

    /**
     * @param array<int, array{task_id: int, keyword: string, link: string}> $updates
     */
    public function __construct(public array $updates)
    {
        $this->onQueue('bulk_blogroll_updates');
    }

    public function handle(): void
    {
        $updated = 0;
        $failed  = 0;

        foreach ($this->updates as $item) {
            $taskId  = (int) ($item['task_id'] ?? 0);
            $keyword = trim((string) ($item['keyword'] ?? ''));
            $link    = trim((string) ($item['link'] ?? ''));

            if ($taskId <= 0 || $keyword === '' || $link === '') {
                continue;
            }

            $task = SidebarCampaignTask::with(['domainRow.domain', 'linkRow'])
                ->whereNotNull('remote_id')
                ->find($taskId);

            if (!$task || !$task->linkRow) {
                $failed++;
                continue;
            }

            $domain = $task->domainRow?->domain;
            if (!$domain || !$domain->api_key) {
                Log::warning('BulkUpdateSidebarBlogrollJob: domain or api_key missing', ['task_id' => $taskId]);
                $failed++;
                continue;
            }

            // ✅ Handle both single remote_id and JSON array of remote_ids
            $remoteIdRaw = $task->remote_id;
            $remoteIds = [];

            if (is_string($remoteIdRaw) && str_starts_with($remoteIdRaw, '[')) {
                $decoded = json_decode($remoteIdRaw, true);
                $remoteIds = is_array($decoded) ? $decoded : [$remoteIdRaw];
            } else {
                $remoteIds = [$remoteIdRaw];
            }

            // ✅ Update all remote entries for this task
            $allSuccess = true;
            foreach ($remoteIds as $remoteId) {
                if (empty($remoteId)) continue;

                // ✅ Build rel array: prioritize raw_rel_attr if available
                $rawRelAttr = trim((string)($task->linkRow->raw_rel_attr ?? ''));

                if ($rawRelAttr !== '') {
                    // Use complete rel string from raw anchor mode
                    $relArray = array_filter(array_map('trim', explode(' ', $rawRelAttr)));
                } else {
                    // Fall back to boolean fields
                    $relArray = array_values(array_filter([
                        ($task->linkRow->nofollow ?? false) ? 'nofollow' : null,
                        ($task->linkRow->sponsored ?? false) ? 'sponsored' : null,
                        ($task->linkRow->ugc ?? false) ? 'ugc' : null,
                        ($task->linkRow->noopener ?? false) ? 'noopener' : null,
                        ($task->linkRow->noreferrer ?? false) ? 'noreferrer' : null,
                    ]));
                }

                $res = BlogrollApiService::updateEntryByRemoteId(
                    $domain->name,
                    $domain->api_key,
                    (string) $remoteId,
                    $keyword,
                    $link,
                    $relArray
                );

                if (!$res->successful()) {
                    $allSuccess = false;
                    Log::warning('BulkUpdateSidebarBlogrollJob: remote update failed', [
                        'task_id' => $taskId,
                        'remote_id' => $remoteId,
                        'domain'  => $domain->name,
                        'body'    => $res->body(),
                    ]);
                }
            }

            if ($allSuccess) {
                $task->linkRow->update([
                    'anchor_keyword' => $keyword,
                    'target_url'    => $link,
                ]);
                $task->update(['content_updated_at' => now()]);
                $updated++;
            } else {
                $task->update(['content_updated_at' => null]);
                $failed++;
            }
        }

        Log::info('BulkUpdateSidebarBlogrollJob: batch completed', [
            'updated' => $updated,
            'failed'  => $failed,
            'total'   => count($this->updates),
        ]);
    }
}
