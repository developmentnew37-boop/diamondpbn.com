<?php

namespace App\Console\Commands;

use App\Models\Admin\Domain;
use App\Services\WordPressAgentStatusService;
use Illuminate\Console\Command;

class DomainsProbeCommand extends Command
{
    protected $signature = 'domains:probe
                            {target : Domain hostname or inventory domain ID}
                            {--save : Persist probe result to the domains table when matched}';

    protected $description = 'Run GET /status, fallback URL, and optional POST /status/check for one domain';

    public function handle(WordPressAgentStatusService $statusService): int
    {
        $target = trim((string) $this->argument('target'));
        $domainModel = is_numeric($target)
            ? Domain::query()->find((int) $target)
            : Domain::findByNormalizedName($target);

        $domainName = $domainModel?->name ?? normalizeDomainName($target);

        if ($domainName === '') {
            $this->error('Could not resolve a domain hostname from the target.');

            return self::FAILURE;
        }

        $apiKey = '';
        if ($domainModel !== null) {
            try {
                $apiKey = trim((string) ($domainModel->api_key ?? ''));
            } catch (\Throwable) {
                $apiKey = '';
            }
        }

        $this->info("Probing {$domainName}...");
        $this->line('  Primary: '.$statusService->primaryUrl($domainName));
        $this->line('  Fallback: '.$statusService->fallbackUrl($domainName));
        $this->line('  Auth: '.$statusService->authenticatedCheckUrl($domainName));
        $this->newLine();

        $public = $statusService->probe($domainName);
        $this->renderResult('Public GET', $public);

        if ($apiKey !== '') {
            $authenticated = $statusService->probeAuthenticated($domainName, $apiKey);
            $this->renderResult('Authenticated POST', $authenticated);
        } else {
            $this->warn('Authenticated POST skipped — no API key in inventory for this domain.');
        }

        $final = $statusService->probeWithAuthFallback($domainName, $apiKey !== '' ? $apiKey : null);
        $this->newLine();
        $this->renderResult('Final (with auth fallback)', $final, true);

        if ($this->option('save') && $domainModel !== null) {
            $domainModel->persistAgentHealth($final);
            $this->info('Saved inventory status: '.($final->ok ? 'connected' : 'disconnected'));
        }

        return $final->ok ? self::SUCCESS : self::FAILURE;
    }

    private function renderResult(string $label, \App\Data\AgentStatusResult $result, bool $highlight = false): void
    {
        $prefix = $highlight ? '<fg=cyan>→</> ' : '  ';
        $status = $result->ok ? '<fg=green>OK</>' : '<fg=red>FAIL</>';
        $this->line("{$prefix}{$label}: {$status} [{$result->code}] via {$result->probeMethod}");

        if ($result->pluginVersion) {
            $this->line("    version: {$result->pluginVersion}");
        }

        if ($result->httpStatus) {
            $this->line("    http: {$result->httpStatus}");
        }

        if ($result->responseTimeMs !== null) {
            $this->line("    time: {$result->responseTimeMs} ms");
        }

        $this->line('    message: '.$result->message);
    }
}
