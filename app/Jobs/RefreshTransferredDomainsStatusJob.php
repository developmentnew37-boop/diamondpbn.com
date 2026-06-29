<?php

namespace App\Jobs;

use App\Models\Admin\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Pool;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
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
    ) {}

    public function handle(): void
    {
        $domains = Domain::query()
            ->whereIn('id', $this->domainIds)
            ->get(['id', 'name']);

        if ($domains->isEmpty()) {
            return;
        }

        $connected = 0;
        $disconnected = 0;

        foreach ($domains->chunk(30) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $domain) {
                    $pool->as((string) $domain->id)
                        ->withoutVerifying()
                        ->timeout(8)
                        ->connectTimeout(5)
                        ->get("https://{$domain->name}/wp-json/external/v1/status");
                }
            });

            foreach ($chunk as $domain) {
                $response = $responses[(string) $domain->id] ?? null;
                $status = 0;

                try {
                    if ($response && $response->successful() && $response->json('status') == true) {
                        $status = 1;
                        $connected++;
                    } else {
                        $disconnected++;
                    }
                } catch (\Throwable) {
                    $disconnected++;
                }

                Domain::where('id', $domain->id)->update(['status' => $status]);
            }
        }

        Log::info('Transferred domains plugin status refresh completed', [
            'total' => $domains->count(),
            'connected' => $connected,
            'disconnected' => $disconnected,
        ]);
    }
}
