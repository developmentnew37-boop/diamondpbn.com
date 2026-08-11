<?php

namespace App\Data;

final readonly class AgentStatusResult
{
    /**
     * @param  array<string, mixed>|null  $json
     */
    public function __construct(
        public bool $ok,
        public string $code,
        public string $message,
        public bool $pluginManagerSupported = false,
        public ?string $pluginVersion = null,
        public string $probeMethod = 'rest',
        public ?int $httpStatus = null,
        public ?int $responseTimeMs = null,
        public ?array $json = null,
    ) {}

    /**
     * Preserve the array contract used by existing plugin-manager callers.
     *
     * @return array<string, mixed>
     */
    public function toLegacyArray(): array
    {
        return [
            'ok' => $this->ok,
            'code' => $this->code,
            'plugin_manager_supported' => $this->pluginManagerSupported,
            'plugin_version' => $this->pluginVersion,
            'message' => $this->message,
            'probe_method' => $this->probeMethod,
            'http_status' => $this->httpStatus,
            'response_time_ms' => $this->responseTimeMs,
            'json' => $this->json,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function healthAttributes(?\DateTimeInterface $previousLastSeenAt = null): array
    {
        return [
            'status' => $this->ok ? 1 : 0,
            'agent_version' => $this->pluginVersion,
            'last_seen_at' => $this->ok ? now() : $previousLastSeenAt,
            'last_status_code' => $this->code,
            'last_status_probe' => $this->probeMethod,
            'last_status_message' => $this->message,
        ];
    }
}
