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

class CheckDomainStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $domainId) {}

    public function handle(WordPressAgentStatusService $statusService): void
    {
        $domain = Domain::query()->find($this->domainId);

        if ($domain === null) {
            Log::warning('Domain status check skipped because domain was deleted', [
                'domain_id' => $this->domainId,
            ]);

            return;
        }

        $apiKey = '';
        try {
            $apiKey = trim((string) ($domain->api_key ?? ''));
        } catch (\Throwable) {
            $apiKey = '';
        }

        $result = $statusService->probeWithAuthFallback((string) $domain->name, $apiKey);
        $domain->forceFill($result->healthAttributes($domain->last_seen_at))->save();

        Log::info("Domain Check → {$domain->name} | Code: {$result->code} | Probe: {$result->probeMethod} | Message: {$result->message}");
    }
}
