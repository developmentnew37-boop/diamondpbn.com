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

            $res = BlogrollApiService::updateEntryByRemoteId(
                $domain->name,
                $domain->api_key,
                (string) $task->remote_id,
                $keyword,
                $link
            );

            if ($res->successful()) {
                $task->link->update([
                    'anchor_keyword' => $keyword,
                    'target_url'     => $link,
                ]);
                $updated++;
            } else {
                $failed++;
                Log::warning('BulkUpdateScheduleSidebarBlogrollJob: remote update failed', [
                    'task_id' => $taskId,
                    'domain'  => $domain->name,
                    'body'    => $res->body(),
                ]);
            }
        }

        Log::info('BulkUpdateScheduleSidebarBlogrollJob: completed', [
            'updated' => $updated,
            'failed'  => $failed,
            'total'   => count($this->updates),
        ]);
    }
}
