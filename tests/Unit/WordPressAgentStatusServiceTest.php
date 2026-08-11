<?php

namespace Tests\Unit;

use App\Data\AgentStatusResult;
use App\Services\WordPressAgentStatusService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressAgentStatusServiceTest extends TestCase
{
    public function test_health_attributes_preserve_last_seen_on_failure(): void
    {
        $lastSeen = Carbon::parse('2026-07-18 12:00:00');
        $result = new AgentStatusResult(
            false,
            'domain_offline',
            'Connection refused',
            probeMethod: 'rest',
            httpStatus: null,
        );

        $attributes = $result->healthAttributes($lastSeen);

        $this->assertSame(0, $attributes['status']);
        $this->assertSame($lastSeen, $attributes['last_seen_at']);
        $this->assertSame('domain_offline', $attributes['last_status_code']);
        $this->assertSame('rest', $attributes['last_status_probe']);
    }

    public function test_it_classifies_a_supported_agent_as_online(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => true,
                'message' => 'Connected',
                'plugin_version' => '8.2.0',
                'plugin_manager_supported' => true,
            ]),
        ]);

        $result = app(WordPressAgentStatusService::class)->probe('https://Example.COM/path');

        $this->assertTrue($result->ok);
        $this->assertSame('online', $result->code);
        $this->assertTrue($result->pluginManagerSupported);
        $this->assertSame('8.2.0', $result->pluginVersion);
        $this->assertSame('rest', $result->probeMethod);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://example.com/wp-json/external/v1/status'
                && ! str_contains($request->url(), 'api_key')
                && ! $request->hasHeader('X-External-API-Key');
        });
    }

    public function test_it_classifies_old_and_unsupported_agents(): void
    {
        Http::fakeSequence()
            ->push([
                'status' => true,
                'plugin_version' => '8.1.4',
                'plugin_manager_supported' => true,
            ])
            ->push([
                'status' => true,
                'plugin_version' => '8.2.0',
                'plugin_manager_supported' => false,
            ]);

        $service = app(WordPressAgentStatusService::class);
        $outdated = $service->probe('old.example');
        $unsupported = $service->probe('unsupported.example');

        $this->assertTrue($outdated->ok);
        $this->assertSame('agent_outdated', $outdated->code);
        $this->assertFalse($outdated->pluginManagerSupported);
        $this->assertTrue($unsupported->ok);
        $this->assertSame('agent_unsupported', $unsupported->code);
    }

    public function test_it_uses_the_opportunistic_fallback_for_html_failures(): void
    {
        Http::fakeSequence()
            ->push('<!DOCTYPE html><title>Blocked</title>', 403)
            ->push([
                'status' => true,
                'plugin_version' => '8.2.0',
                'plugin_manager_supported' => true,
            ]);

        $result = app(WordPressAgentStatusService::class)->probe('fallback.example');

        $this->assertTrue($result->ok);
        $this->assertSame('online', $result->code);
        $this->assertSame('fallback', $result->probeMethod);
    }

    public function test_primary_html_403_is_not_hidden_by_fallback_404(): void
    {
        Http::fakeSequence()
            ->push('<html><body>Wordfence: Access denied</body></html>', 403)
            ->push(['message' => 'Not found'], 404);

        $result = app(WordPressAgentStatusService::class)->probe('blocked.example');

        $this->assertFalse($result->ok);
        $this->assertSame('firewall_blocked', $result->code);
        $this->assertSame('rest', $result->probeMethod);
        $this->assertSame(403, $result->httpStatus);
    }

    public function test_primary_firewall_evidence_is_preserved_when_fallback_cannot_connect(): void
    {
        Http::fakeSequence()
            ->push('<html><body>Wordfence: Access denied</body></html>', 403)
            ->pushFailedConnection('Fallback connection refused');

        $result = app(WordPressAgentStatusService::class)->probe('blocked.example');

        $this->assertFalse($result->ok);
        $this->assertSame('firewall_blocked', $result->code);
        $this->assertSame('rest', $result->probeMethod);
        $this->assertSame(403, $result->httpStatus);
    }

    public function test_pooled_probes_preserve_keys_and_use_pooled_fallbacks(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'blocked.example/wp-json')) {
                return Http::response('<html>Access denied</html>', 403);
            }

            return Http::response([
                'status' => true,
                'plugin_version' => '8.2.0',
                'plugin_manager_supported' => true,
            ]);
        });

        $results = app(WordPressAgentStatusService::class)->probeMany([
            10 => 'online.example',
            20 => 'blocked.example',
        ]);

        $this->assertSame([10, 20], array_keys($results));
        $this->assertSame('online', $results[10]->code);
        $this->assertSame('online', $results[20]->code);
        $this->assertSame('fallback', $results[20]->probeMethod);
    }

    public function test_it_rejects_successful_non_json_status_pages(): void
    {
        Http::fake([
            '*' => Http::response('plain text response', 200),
        ]);

        $result = app(WordPressAgentStatusService::class)->probe('invalid.example');

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_status_response', $result->code);
    }

    public function test_authenticated_post_check_sends_api_key_header_and_body(): void
    {
        Http::fake([
            'https://auth.example/wp-json/external/v1/status/check' => Http::response([
                'status' => true,
                'message' => 'Connected',
                'authenticated' => true,
                'plugin_version' => '8.2.0',
                'plugin_manager_supported' => true,
            ]),
        ]);

        $result = app(WordPressAgentStatusService::class)
            ->probeAuthenticated('auth.example', 'secret-key-123');

        $this->assertTrue($result->ok);
        $this->assertSame('online', $result->code);
        $this->assertSame('auth_rest', $result->probeMethod);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://auth.example/wp-json/external/v1/status/check'
                && $request->header('X-External-API-Key')[0] === 'secret-key-123'
                && $request['api_key'] === 'secret-key-123';
        });
    }

    public function test_authenticated_post_check_classifies_bad_api_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'code' => 'bad_key',
                'message' => 'Invalid API key',
            ], 403),
        ]);

        $result = app(WordPressAgentStatusService::class)
            ->probeAuthenticated('badkey.example', 'wrong-key');

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_api_key', $result->code);
        $this->assertSame('auth_rest', $result->probeMethod);
    }

    public function test_auth_fallback_recovers_when_public_get_is_firewall_blocked(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/status/check')) {
                return Http::response([
                    'status' => true,
                    'authenticated' => true,
                    'plugin_version' => '8.2.0',
                    'plugin_manager_supported' => true,
                ]);
            }

            return Http::response('<html><body>Wordfence Access Denied</body></html>', 403);
        });

        $result = app(WordPressAgentStatusService::class)
            ->probeWithAuthFallback('blocked.example', 'valid-key');

        $this->assertTrue($result->ok);
        $this->assertSame('online', $result->code);
        $this->assertSame('auth_rest', $result->probeMethod);
    }

    public function test_auth_fallback_keeps_public_result_when_no_api_key(): void
    {
        Http::fake([
            '*' => Http::response('<html><body>Wordfence Access Denied</body></html>', 403),
        ]);

        $result = app(WordPressAgentStatusService::class)
            ->probeWithAuthFallback('blocked.example', null);

        $this->assertFalse($result->ok);
        $this->assertSame('firewall_blocked', $result->code);
        $this->assertSame('rest', $result->probeMethod);
    }
}
