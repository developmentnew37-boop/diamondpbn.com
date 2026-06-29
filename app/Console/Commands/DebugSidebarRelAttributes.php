<?php

namespace App\Console\Commands;

use App\Models\Admin\SidebarCampaignTask;
use Illuminate\Console\Command;

class DebugSidebarRelAttributes extends Command
{
    protected $signature = 'debug:sidebar-rel {task_id?}';

    protected $description = 'Debug rel attributes for sidebar campaign tasks';

    public function handle()
    {
        $taskId = $this->argument('task_id');

        if ($taskId) {
            $task = SidebarCampaignTask::with(['linkRow', 'domainRow.domain'])->find($taskId);
            if (! $task) {
                $this->error("Task {$taskId} not found");

                return 1;
            }
            $tasks = collect([$task]);
        } else {
            $tasks = SidebarCampaignTask::with(['linkRow', 'domainRow.domain'])
                ->where('status', 'success')
                ->latest('published_at')
                ->limit(5)
                ->get();
        }

        if ($tasks->isEmpty()) {
            $this->warn('No published tasks found');

            return 0;
        }

        foreach ($tasks as $task) {
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("Task ID: {$task->id}");
            $this->info("Status: {$task->status}");
            $this->info("Published: {$task->published_at}");
            $this->info("Remote ID: {$task->remote_id}");

            if ($task->domainRow && $task->domainRow->domain) {
                $this->info("Domain: {$task->domainRow->domain->name}");
            }

            if ($task->linkRow) {
                $link = $task->linkRow;
                $this->newLine();
                $this->info('Link Attributes:');
                $this->line("  Keyword: {$link->anchor_keyword}");
                $this->line("  URL: {$link->target_url}");
                $this->newLine();

                $this->info('Rel Attributes (from database):');
                $this->line('  nofollow: '.($link->nofollow ? '✓ YES' : '✗ NO'));
                $this->line('  sponsored: '.($link->sponsored ? '✓ YES' : '✗ NO'));
                $this->line('  ugc: '.($link->ugc ? '✓ YES' : '✗ NO'));
                $this->line('  noopener: '.($link->noopener ? '✓ YES' : '✗ NO'));
                $this->line('  noreferrer: '.($link->noreferrer ? '✓ YES' : '✗ NO'));

                // Build what should be sent
                $rel = [];
                if ($link->nofollow) {
                    $rel[] = 'nofollow';
                }
                if ($link->sponsored) {
                    $rel[] = 'sponsored';
                }
                if ($link->ugc) {
                    $rel[] = 'ugc';
                }
                if ($link->noopener) {
                    $rel[] = 'noopener';
                }
                if ($link->noreferrer) {
                    $rel[] = 'noreferrer';
                }

                $this->newLine();
                $this->info('What Laravel sends to WordPress:');
                $this->line('  rel array: ['.implode(', ', $rel).']');
                $this->line("  rel_attr string: '".implode(' ', $rel)."'");

                $this->newLine();
                $this->info('Expected HTML output:');
                $relAttr = ! empty($rel) ? ' rel="'.implode(' ', $rel).'"' : '';
                $this->line("  <a href=\"{$link->target_url}\"{$relAttr} target=\"_blank\">{$link->anchor_keyword}</a>");
            }

            $this->newLine();
        }

        $this->newLine();
        $this->warn('⚠️  If rel attributes are not appearing on WordPress:');
        $this->line('1. The WordPress plugin needs to be updated');
        $this->line('2. Check: wp-content/plugins/[your-blogroll-plugin]/');
        $this->line("3. The plugin must read 'ugc', 'noopener', 'noreferrer' from API");
        $this->line('4. The plugin must render them in the HTML output');

        return 0;
    }
}
