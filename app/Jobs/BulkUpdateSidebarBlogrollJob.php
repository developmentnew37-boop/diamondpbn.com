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

            $res = BlogrollApiService::updateEntryByRemoteId(
                $domain->name,
                $domain->api_key,
                $task->remote_id,
                $keyword,
                $link,
                array_values(array_filter([
                    ($task->linkRow->nofollow ?? false) ? 'nofollow' : null,
                    ($task->linkRow->sponsored ?? false) ? 'sponsored' : null,
                ]))
            );

            if ($res->successful()) {
                $task->linkRow->update([
                    'anchor_keyword' => $keyword,
                    'target_url'    => $link,
                ]);
                $task->update(['content_updated_at' => now()]);
                $updated++;
            } else {
                $task->update(['content_updated_at' => null]);
                $failed++;
                Log::warning('BulkUpdateSidebarBlogrollJob: remote update failed', [
                    'task_id' => $taskId,
                    'domain'  => $domain->name,
                    'body'    => $res->body(),
                ]);
            }
        }

        Log::info('BulkUpdateSidebarBlogrollJob: batch completed', [
            'updated' => $updated,
            'failed'  => $failed,
            'total'   => count($this->updates),
        ]);
    }
}
