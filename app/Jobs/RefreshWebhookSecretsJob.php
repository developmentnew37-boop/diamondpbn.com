<?php

namespace App\Jobs;

use App\Services\WebhookSecretRotationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshWebhookSecretsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'webhook-secret';

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue((string) config('webhook.rotation_queue', self::QUEUE));
    }

    public function handle(WebhookSecretRotationService $rotationService): void
    {
        if (! $rotationService->isAutoRotationEnabled()) {
            Log::info('Webhook secret auto-rotation skipped (disabled in settings)');

            return;
        }

        $result = $rotationService->rotateDueSecrets();

        if ($result['rotated'] === 0) {
            Log::info('Webhook secret auto-rotation: no secrets due yet', [
                'rotation_hours' => $result['rotation_hours'],
            ]);

            return;
        }

        Log::warning('Webhook secrets auto-rotated by scheduled job', $result);
    }
}
