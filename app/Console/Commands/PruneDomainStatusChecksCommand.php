<?php

namespace App\Console\Commands;

use App\Jobs\PruneDomainStatusChecksJob;
use App\Services\DomainStatusCheckCleanupService;
use Illuminate\Console\Command;

class PruneDomainStatusChecksCommand extends Command
{
    protected $signature = 'domain-status-checks:prune
                            {--admin= : Only trim history for this admin ID}
                            {--sync : Run in this process (default: dispatch queue job)}';

    protected $description = 'Prune old domain status check runs (batched, safe for large tables)';

    public function handle(DomainStatusCheckCleanupService $cleanup): int
    {
        $adminId = $this->option('admin');
        $adminId = $adminId !== null && $adminId !== '' ? (int) $adminId : null;

        if ($this->option('sync')) {
            $result = $cleanup->runBatchedCleanup($adminId);
            $this->printResult($result);

            return self::SUCCESS;
        }

        PruneDomainStatusChecksJob::dispatch($adminId);
        $this->info('Prune job dispatched to default queue (batched, non-blocking).');

        return self::SUCCESS;
    }

    /**
     * @param  array{disconnected?: int, status_checker?: int, stale: int, expired: int, trimmed: int, total: int, capped?: bool}  $result
     */
    private function printResult(array $result): void
    {
        $capped = ! empty($result['capped']) ? ' (budget cap reached — run again for more)' : '';

        $this->info(sprintf(
            'Pruned %d check run(s): %d disconnected, %d status checker, %d stale, %d expired, %d trimmed%s.',
            $result['total'],
            $result['disconnected'] ?? 0,
            $result['status_checker'] ?? 0,
            $result['stale'],
            $result['expired'],
            $result['trimmed'],
            $capped
        ));
    }
}
