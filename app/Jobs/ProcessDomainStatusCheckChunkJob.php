<?php

namespace App\Jobs;

use App\Models\Admin\DomainStatusCheck;
use App\Services\DomainStatusCheckerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessDomainStatusCheckChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $checkId,
        public string $phase = 'initial'
    ) {
        $this->onQueue('domainCheck');
        $this->timeout = app(DomainStatusCheckerService::class)->jobTimeoutSeconds();
    }

    public function handle(DomainStatusCheckerService $checker): void
    {
        $check = DomainStatusCheck::query()->find($this->checkId);

        if (! $check || $check->isFinished()) {
            return;
        }

        if ($this->phase === 'retry' && $check->phase !== 'retry') {
            $check->update(['phase' => 'retry']);
        }

        $lock = Cache::lock('domain_status_check:'.$check->id, $checker->lockSeconds());

        if (! $lock->get()) {
            self::dispatch($check->id, $this->phase)
                ->delay(now()->addSeconds(3))
                ->onQueue('domainCheck');

            return;
        }

        try {
            $check->refresh();

            if ($check->isFinished()) {
                return;
            }

            $checker->processNextChunk($check);
            $check->refresh();

            if (! $check->isFinished()) {
                self::dispatch($check->id, $check->phase === 'retry' ? 'retry' : 'initial')
                    ->onQueue('domainCheck');
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        $checker = app(DomainStatusCheckerService::class);
        $check = DomainStatusCheck::query()->find($this->checkId);

        if (! $check || $check->isFinished()) {
            Log::error('Domain status check job failed', [
                'check_id' => $this->checkId,
                'phase' => $this->phase,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $checker->recoverOrphanedItems($check);
        $check->refresh();

        // Fatal code errors should stop the loop; restart the queue worker after deploying.
        if ($exception instanceof \Error) {
            $check->update([
                'status' => 'failed',
                'status_message' => 'Background check failed: '.$exception->getMessage()
                    .' Restart the domainCheck queue worker and try again.',
                'completed_at' => now(),
            ]);

            Log::error('Domain status check job failed with fatal error', [
                'check_id' => $this->checkId,
                'phase' => $this->phase,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $pendingStatus = $check->phase === 'retry' ? 'retry_pending' : 'pending';
        $hasPending = $check->items()->where('check_status', $pendingStatus)->exists();

        if ($hasPending) {
            $check->update([
                'status' => 'processing',
                'status_message' => 'Resuming after worker interruption...',
            ]);

            self::dispatch($check->id, $check->phase === 'retry' ? 'retry' : 'initial')
                ->delay(now()->addSeconds(5))
                ->onQueue('domainCheck');

            Log::warning('Domain status check job failed; re-queued pending items', [
                'check_id' => $this->checkId,
                'phase' => $this->phase,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $check->update([
            'status' => 'failed',
            'status_message' => 'Background check failed: '.$exception->getMessage(),
            'completed_at' => now(),
        ]);

        Log::error('Domain status check job failed', [
            'check_id' => $this->checkId,
            'phase' => $this->phase,
            'error' => $exception->getMessage(),
        ]);
    }
}
