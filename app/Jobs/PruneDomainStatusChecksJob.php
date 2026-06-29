<?php

namespace App\Jobs;

use App\Services\DomainStatusCheckCleanupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PruneDomainStatusChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public ?int $adminId = null
    ) {
        $this->onQueue('default');
    }

    public function handle(DomainStatusCheckCleanupService $cleanup): void
    {
        $lock = Cache::lock('domain_status_check_prune', 300);

        if (! $lock->get()) {
            return;
        }

        try {
            $result = $cleanup->runBatchedCleanup($this->adminId);

            if ($result['total'] > 0) {
                Log::info('Domain status check prune job finished', $result);
            }

            if (! empty($result['capped'])) {
                self::dispatch($this->adminId)->delay(now()->addMinutes(2));
            }
        } finally {
            $lock->release();
        }
    }
}
