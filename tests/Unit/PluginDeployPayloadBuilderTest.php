<?php

namespace Tests\Unit;

use App\Models\Admin\PluginPackage;
use App\Services\PluginDeployPayloadBuilder;
use Tests\TestCase;

class PluginDeployPayloadBuilderTest extends TestCase
{
    public function test_delete_omits_library_package_version_unless_remote_version_is_given(): void
    {
        $package = new PluginPackage([
            'slug' => 'hidden-link-1.3.0',
            'expected_slug' => 'pbn-hidden-link-manager',
            'version' => '1.3.0',
        ]);
        $builder = new PluginDeployPayloadBuilder;

        $withoutRemote = $builder->delete($package, 'pbn-hidden-link-manager/plugin.php');
        $this->assertArrayNotHasKey('target_version', $withoutRemote);
        $this->assertSame('pbn-hidden-link-manager/plugin.php', $withoutRemote['plugin_file']);
        $this->assertSame('pbn-hidden-link-manager', $withoutRemote['expected_slug']);

        $withRemote = $builder->delete($package, 'pbn-hidden-link-manager/plugin.php', '1.1.5');
        $this->assertSame('1.1.5', $withRemote['target_version']);
        $this->assertNotSame('1.3.0', $withRemote['target_version']);
    }

    public function test_activate_omits_version_when_remote_version_unknown(): void
    {
        $package = new PluginPackage([
            'slug' => 'sample-1.2.3',
            'expected_slug' => 'sample',
            'version' => '1.2.3',
        ]);
        $builder = new PluginDeployPayloadBuilder;

        $payload = $builder->activateOrDeactivate($package);
        $this->assertArrayNotHasKey('target_version', $payload);
        $this->assertSame('sample', $payload['expected_slug']);
    }
}
