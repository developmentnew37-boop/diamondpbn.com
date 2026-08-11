<?php

namespace App\Console\Commands;

use App\Jobs\RefreshTransferredDomainsStatusJob;
use App\Models\Admin\Domain;
use Illuminate\Console\Command;

class DomainsHealthCheckCommand extends Command
{
    protected $signature = 'domains:health-check {--chunk=100 : Domains dispatched per queue job}';

    protected $description = 'Queue live WordPress agent health checks for all domains';

    public function handle(): int
    {
        $chunkSize = max(1, min(500, (int) $this->option('chunk')));
        $jobs = 0;
        $domains = 0;

        Domain::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$jobs, &$domains) {
                $ids = $rows->pluck('id')->map(static fn ($id) => (int) $id)->all();

                if ($ids === []) {
                    return;
                }

                RefreshTransferredDomainsStatusJob::dispatch($ids)
                    ->onQueue((string) config('domain_status_checker.health_sync_queue', 'domainHealthSync'));
                $jobs++;
                $domains += count($ids);
            });

        $this->info("Queued {$domains} domain(s) in {$jobs} health-check job(s).");

        return self::SUCCESS;
    }
}
