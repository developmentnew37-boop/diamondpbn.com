<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Services\BlogrollApiService;

class BulkUpdateScheduleSidebarBlogrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public array $updates)
    {
        $this->onQueue('schedule_sidebar_bulk_updates');
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

            $task = ScheduleSidebarCampaignTask::with(['domain.domain', 'link'])
                ->whereNotNull('remote_id')
                ->find($taskId);

            if (!$task || !$task->link) {
                $failed++;
                continue;
            }

            $domain = $task->domain?->domain;
            if (!$domain || !$domain->api_key) {
                Log::warning('BulkUpdateScheduleSidebarBlogrollJob: domain or api_key missing', ['task_id' => $taskId]);
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
                $rawRelAttr = trim((string)($task->link->raw_rel_attr ?? ''));

                if ($rawRelAttr !== '') {
                    // Use complete rel string from raw anchor mode
                    $relArray = array_filter(array_map('trim', explode(' ', $rawRelAttr)));
                } else {
                    // Fall back to boolean fields
                    $relArray = array_values(array_filter([
                        ($task->link->nofollow ?? false) ? 'nofollow' : null,
                        ($task->link->sponsored ?? false) ? 'sponsored' : null,
                        ($task->link->ugc ?? false) ? 'ugc' : null,
                        ($task->link->noopener ?? false) ? 'noopener' : null,
                        ($task->link->noreferrer ?? false) ? 'noreferrer' : null,
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
                    Log::warning('BulkUpdateScheduleSidebarBlogrollJob: remote update failed', [
                        'task_id' => $taskId,
                        'remote_id' => $remoteId,
                        'domain'  => $domain->name,
                        'body'    => $res->body(),
                    ]);
                }
            }

            if ($allSuccess) {
                $task->link->update([
                    'anchor_keyword' => $keyword,
                    'target_url'     => $link,
                ]);
                $updated++;
            } else {
                $failed++;
            }
        }

        Log::info('BulkUpdateScheduleSidebarBlogrollJob: completed', [
            'updated' => $updated,
            'failed'  => $failed,
            'total'   => count($this->updates),
        ]);
    }
}
