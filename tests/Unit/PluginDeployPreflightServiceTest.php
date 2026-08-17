<?php

namespace Tests\Unit;

use App\Models\Admin\PluginPackage;
use App\Services\PluginDeployPreflightService;
use Tests\TestCase;

class PluginDeployPreflightServiceTest extends TestCase
{
    public function test_folder_match_uses_plugin_file_directory_when_slug_is_text_domain(): void
    {
        $service = new PluginDeployPreflightService;
        $package = $this->package();

        $match = $service->findFolderMatch([
            [
                'slug' => 'sample-text-domain',
                'version' => '1.2.3',
                'plugin_file' => 'sample/sample.php',
                'active' => true,
            ],
        ], $package);

        $this->assertNotNull($match);
        $this->assertSame('sample', $match['slug']);
        $this->assertSame('sample/sample.php', $match['plugin_file']);
        $this->assertSame('delete', $service->resolveOperation('delete', [[
            'slug' => 'sample-text-domain',
            'version' => '1.2.3',
            'plugin_file' => 'sample/sample.php',
        ]], $package));
    }

    public function test_delete_resolves_via_unique_name_and_version(): void
    {
        $service = new PluginDeployPreflightService;
        $package = $this->package();
        $inventory = [[
            'slug' => 'legacy-folder',
            'name' => 'Sample Plugin',
            'version' => '1.2.3',
            'plugin_file' => 'legacy-folder/plugin.php',
        ]];

        $this->assertSame('delete', $service->resolveOperation('delete', $inventory, $package));
        $match = $service->findInstalledMatch($inventory, $package);
        $this->assertSame('legacy-folder/plugin.php', $match['plugin_file'] ?? null);
    }

    public function test_delete_skips_when_name_matches_multiple_plugins(): void
    {
        $service = new PluginDeployPreflightService;
        $package = $this->package();
        $inventory = [
            [
                'slug' => 'a',
                'name' => 'Sample Plugin',
                'version' => '1.2.3',
                'plugin_file' => 'a/a.php',
            ],
            [
                'slug' => 'b',
                'name' => 'Sample Plugin',
                'version' => '1.2.3',
                'plugin_file' => 'b/b.php',
            ],
        ];

        $this->assertSame('skip', $service->resolveOperation('delete', $inventory, $package));
    }

    private function package(): PluginPackage
    {
        return new PluginPackage([
            'slug' => 'sample-version-1.2.3',
            'expected_slug' => 'sample',
            'name' => 'Sample Plugin',
            'version' => '1.2.3',
        ]);
    }
}
