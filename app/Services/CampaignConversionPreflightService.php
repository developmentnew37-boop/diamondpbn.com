<?php

namespace App\Services;

use App\Models\Admin\Domain;

class CampaignConversionPreflightService
{
    public function __construct(
        private readonly WordPressAgentStatusService $statusService,
    ) {}

    /**
     * @param  int[]  $domainIds
     * @return array<int, array{
     *   domain_id: int,
     *   domain_name: string,
     *   ok: bool,
     *   code: string|null,
     *   message: string,
     *   agent_version: string|null,
     *   meets_minimum: bool,
     *   latency_ms: int|null
     * }>
     */
    public function checkDomains(array $domainIds, string $minimumVersion = CampaignConversionEligibilityService::MIN_AGENT_VERSION): array
    {
        $domains = Domain::query()
            ->whereIn('id', array_unique($domainIds))
            ->get(['id', 'name', 'api_key']);

        $results = [];

        foreach ($domains as $domain) {
            $result = $this->statusService->probeAuthenticated((string) $domain->name, (string) ($domain->api_key ?? ''));
            $version = $result->pluginVersion;
            $meets = $version !== null && version_compare($version, $minimumVersion, '>=');

            $results[] = [
                'domain_id' => (int) $domain->id,
                'domain_name' => (string) $domain->name,
                'ok' => $result->ok && $meets,
                'code' => $result->code,
                'message' => $result->message,
                'agent_version' => $version,
                'meets_minimum' => $meets,
                'latency_ms' => $result->responseTimeMs,
            ];
        }

        return $results;
    }
}
