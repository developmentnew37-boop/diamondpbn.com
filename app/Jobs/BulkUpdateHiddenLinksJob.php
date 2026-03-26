<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Services\HiddenLinksApiService;

/**
 * Update hidden link tasks on remote (one batch). Each item: ['task_id' => int, 'keyword' => string, 'link' => string].
 */
class BulkUpdateHiddenLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    /** @param array<int, array{task_id: int, keyword: string, link: string}> $updates */
    public function __construct(public array $updates)
    {
        $this->onQueue('update_hidden_links');
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

            $task = HiddenLinksCampaignTasks::with(['domainRow.domain', 'linkRow'])
                ->whereNotNull('remote_id')
                ->find($taskId);

            if (!$task || !$task->linkRow) {
                $failed++;
                continue;
            }

            $domain = $task->domainRow?->domain;
            if (!$domain || !$domain->api_key) {
                Log::warning('BulkUpdateHiddenLinksJob: domain or api_key missing', ['task_id' => $taskId]);
                $task->update(['content_updated_at' => null]);
                $failed++;
                continue;
            }

            $res = HiddenLinksApiService::updateEntry($domain->name, $domain->api_key, $task->remote_id, $keyword, $link);

            if ($res->successful()) {
                $task->linkRow->update([
                    'anchor_keyword' => $keyword,
                    'target_url'     => $link,
                ]);
                $task->update(['content_updated_at' => now()]);
                $updated++;
            } else {
                $task->update(['content_updated_at' => null]);
                $failed++;
                Log::warning('BulkUpdateHiddenLinksJob: remote update failed', [
                    'task_id' => $taskId,
                    'body'    => $res->body(),
                ]);
            }
        }

        Log::info('BulkUpdateHiddenLinksJob: batch completed', [
            'updated' => $updated,
            'failed'  => $failed,
            'total'   => count($this->updates),
        ]);
    }
}
