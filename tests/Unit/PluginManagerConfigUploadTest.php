<?php

namespace Tests\Unit;

use Tests\TestCase;

class PluginManagerConfigUploadTest extends TestCase
{
    public function test_hardening_defaults_match_the_agent_contract(): void
    {
        $this->assertSame('8.1.5', config('plugin_manager.min_agent_version'));
        $this->assertSame(50, config('plugin_manager.agent_max_zip_mb'));
        $this->assertSame(50 * 1024 * 1024, config('plugin_manager.max_zip_bytes'));
        $this->assertSame(300, config('plugin_manager.download_timeout'));
        $this->assertTrue(config('plugin_manager.require_inventory'));
        $this->assertFalse(config('plugin_manager.insecure_tls'));
        $this->assertSame(15, config('plugin_manager.status_timeout'));
        $this->assertSame(10, config('plugin_manager.status_connect_timeout'));
        $this->assertSame(2, config('plugin_manager.http_retry_attempts'));
    }

    public function test_effective_upload_limit_uses_the_smallest_live_ceiling_without_disabling_uploads(): void
    {
        config()->set('plugin_manager.dashboard_max_zip_mb', 50);
        config()->set('plugin_manager.agent_max_zip_mb', 50);

        $limits = pluginManagerUploadLimits(
            phpUploadBytes: 12 * 1024 * 1024,
            phpPostBytes: 14 * 1024 * 1024,
        );

        $this->assertSame(50, $limits['max_zip_mb']);
        $this->assertSame(50, $limits['agent_max_zip_mb']);
        $this->assertSame(12 * 1024 * 1024, $limits['effective_bytes']);
        $this->assertSame(12 * 1024, $limits['validation_kb']);
        $this->assertFalse($limits['server_ready']);
        $this->assertGreaterThan(0, $limits['validation_kb']);
    }

    public function test_legacy_dashboard_zip_setting_remains_supported(): void
    {
        config()->set('plugin_manager.dashboard_max_zip_mb', null);
        config()->set('plugin_manager.max_zip_mb', 9);

        $limits = pluginManagerUploadLimits(20 * 1024 * 1024, 20 * 1024 * 1024);

        $this->assertSame(9, $limits['max_zip_mb']);
        $this->assertSame(9 * 1024 * 1024, $limits['effective_bytes']);
        $this->assertTrue($limits['server_ready']);
    }

    public function test_effective_upload_limit_never_exceeds_the_agent_ceiling(): void
    {
        config()->set('plugin_manager.dashboard_max_zip_mb', 100);
        config()->set('plugin_manager.agent_max_zip_mb', 50);

        $limits = pluginManagerUploadLimits(100 * 1024 * 1024, 100 * 1024 * 1024);

        $this->assertSame(50 * 1024 * 1024, $limits['effective_bytes']);
        $this->assertSame(50 * 1024, $limits['validation_kb']);
    }

    public function test_low_server_ceiling_warns_without_blanket_controller_rejection(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Admin/PluginPackageController.php')
        );
        $view = file_get_contents(
            resource_path('views/admin/domains/plugin-manager/index.blade.php')
        );

        $this->assertStringNotContainsString("if (! \$limits['server_ready'])", $controller);
        $this->assertStringContainsString('can still be uploaded now', $view);
        $this->assertStringContainsString("max:'.\$limits['validation_kb']", $controller);
    }
}
