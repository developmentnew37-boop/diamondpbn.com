<?php

namespace App\Console\Commands;

use App\Services\DomainStatusCheckerService;
use Illuminate\Console\Command;

class CancelStuckDomainStatusChecksCommand extends Command
{
    protected $signature = 'domain-status-checks:cancel-stuck
                            {--minutes= : Only cancel runs idle for at least this many minutes}
                            {--dry-run : Show what would be cancelled without changing data}';

    protected $description = 'Cancel stuck/incomplete domain status checks and purge leftover domainCheck chunk jobs';

    public function handle(DomainStatusCheckerService $checker): int
    {
        $minutes = $this->option('minutes');
        $minutes = $minutes !== null && $minutes !== '' ? (int) $minutes : null;

        if ($this->option('dry-run')) {
            $query = \App\Models\Admin\DomainStatusCheck::query()
                ->whereIn('status', ['queued', 'processing']);
            if ($minutes !== null && $minutes > 0) {
                $query->where('updated_at', '<=', now()->subMinutes($minutes));
            }
            $count = $query->count();
            $this->info("Would cancel {$count} stuck check run(s).");

            return self::SUCCESS;
        }

        $result = $checker->cancelStuckChecks($minutes);

        $this->info(sprintf(
            'Cancelled %d check run(s); purged %d queue job row(s).',
            $result['cancelled'],
            $result['jobs_purged']
        ));

        return self::SUCCESS;
    }
}
