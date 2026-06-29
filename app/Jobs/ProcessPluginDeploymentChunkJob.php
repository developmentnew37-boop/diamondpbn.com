<?php

namespace App\Jobs;

use App\Models\Admin\PluginDeployment;
use App\Services\PluginDeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPluginDeploymentChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $deploymentId
    ) {
        $this->onQueue('plugin_deployments');
        $this->timeout = app(PluginDeploymentService::class)->jobTimeoutSeconds();
    }

    public function handle(PluginDeploymentService $deploymentService): void
    {
        $deployment = PluginDeployment::query()->find($this->deploymentId);

        if (! $deployment || $deployment->isFinished()) {
            return;
        }

        $lock = Cache::lock('plugin_deployment:'.$deployment->id, $deploymentService->lockSeconds());

        if (! $lock->get()) {
            self::dispatch($deployment->id)
                ->delay(now()->addSeconds(3))
                ->onQueue('plugin_deployments');

            return;
        }

        try {
            $deployment->refresh();

            if ($deployment->isFinished() || $deployment->isCancelled()) {
                return;
            }

            $deploymentService->processNextChunk($deployment);
            $deployment->refresh();

            if (! $deployment->isFinished() && ! $deployment->isCancelled()) {
                $hasPending = $deployment->items()->where('item_status', 'pending')->exists();
                if ($hasPending) {
                    self::dispatch($deployment->id)->onQueue('plugin_deployments');
                }
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        $deploymentService = app(PluginDeploymentService::class);
        $deployment = PluginDeployment::query()->find($this->deploymentId);

        if (! $deployment || $deployment->isFinished()) {
            Log::error('Plugin deployment job failed', [
                'deployment_id' => $this->deploymentId,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $deploymentService->recoverOrphanedItems($deployment);
        $deployment->refresh();

        $hasPending = $deployment->items()->where('item_status', 'pending')->exists();

        if ($hasPending && ! $deployment->isCancelled()) {
            $deployment->update([
                'status' => 'running',
                'status_message' => 'Resuming after worker interruption...',
            ]);

            self::dispatch($deployment->id)->onQueue('plugin_deployments');

            Log::warning('Plugin deployment job failed; re-queued pending items', [
                'deployment_id' => $this->deploymentId,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $deployment->update([
            'status' => 'failed',
            'status_message' => 'Background deployment failed: '.$exception->getMessage(),
            'completed_at' => now(),
        ]);

        Log::error('Plugin deployment job failed', [
            'deployment_id' => $this->deploymentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
