<?php

namespace App\Jobs;

use App\Models\Admin\Domain;
use App\Services\WordPressAgentStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshTransferredDomainsStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    /**
     * @param  array<int>  $domainIds
     */
    public function __construct(
        public array $domainIds
    ) {
        $this->onQueue((string) config('domain_status_checker.health_sync_queue', 'domainHealthSync'));
    }

    public function handle(WordPressAgentStatusService $statusService): void
    {
        $domains = Domain::query()
            ->whereIn('id', $this->domainIds)
            ->get();

        if ($domains->isEmpty()) {
            return;
        }

        $requestTimeout = max(5, (int) config('domain_status_checker.request_timeout', 60));
        $connectTimeout = max(2, (int) config('domain_status_checker.connect_timeout', 20));

        $connected = 0;
        $disconnected = 0;

        foreach ($domains->chunk(30) as $chunk) {
            $targets = $chunk->mapWithKeys(function (Domain $domain) {
                try {
                    $apiKey = trim((string) ($domain->api_key ?? ''));
                } catch (\Throwable) {
                    $apiKey = '';
                }

                return [
                    $domain->id => [
                        'domain' => $domain->name,
                        'api_key' => $apiKey,
                    ],
                ];
            })->all();

            $responses = $statusService->probeManyWithAuthFallback($targets, $requestTimeout, $connectTimeout);

            foreach ($chunk as $domain) {
                $result = $responses[$domain->id] ?? null;

                if ($result?->ok) {
                    $connected++;
                } else {
                    $disconnected++;
                }

                if ($result !== null) {
                    $domain->persistAgentHealth($result);
                }
            }
        }

        Log::info('Transferred domains plugin status refresh completed', [
            'total' => $domains->count(),
            'connected' => $connected,
            'disconnected' => $disconnected,
        ]);
    }
}
