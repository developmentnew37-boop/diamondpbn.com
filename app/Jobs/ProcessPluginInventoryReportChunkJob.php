<?php

namespace App\Jobs;

use App\Models\Admin\PluginInventoryReport;
use App\Services\PluginInventoryReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPluginInventoryReportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $reportId
    ) {
        $this->onQueue('plugin_deployments');
        $this->timeout = app(PluginInventoryReportService::class)->jobTimeoutSeconds();
    }

    public function handle(PluginInventoryReportService $reportService): void
    {
        $report = PluginInventoryReport::query()->find($this->reportId);

        if (! $report || $report->isFinished()) {
            return;
        }

        $lock = Cache::lock('plugin_inventory_report:'.$report->id, $reportService->lockSeconds());

        if (! $lock->get()) {
            self::dispatch($report->id)
                ->delay(now()->addSeconds(3))
                ->onQueue('plugin_deployments');

            return;
        }

        $continue = false;

        try {
            $report->refresh();

            if ($report->isFinished()) {
                return;
            }

            $reportService->processNextChunk($report);
            $report->refresh();

            $continue = ! $report->isFinished()
                && (int) $report->processed_count < (int) $report->total_count;
        } finally {
            $lock->release();
        }

        if ($continue) {
            self::dispatch($report->id)->onQueue('plugin_deployments');
        }
    }

    public function failed(Throwable $exception): void
    {
        $report = PluginInventoryReport::query()->find($this->reportId);

        Log::error('Plugin inventory report job failed', [
            'report_id' => $this->reportId,
            'error' => $exception->getMessage(),
        ]);

        if (! $report || $report->isFinished()) {
            return;
        }

        $report->update([
            'status' => 'failed',
            'completed_at' => now(),
            'status_message' => 'Report stopped: '.$exception->getMessage(),
        ]);
    }
}
