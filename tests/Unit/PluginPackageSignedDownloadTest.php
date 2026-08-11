<?php

namespace Tests\Unit;

use App\Models\Admin\PluginDeployment;
use App\Models\Admin\PluginPackage;
use App\Services\PluginPackageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PluginPackageSignedDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('plugin_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestamps();
        });
        Schema::create('plugin_deployments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('plugin_package_id');
            $table->timestamps();
        });

        config()->set('plugin_manager.signing_key', 'test-signing-key');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('plugin_deployments');
        Schema::dropIfExists('plugin_packages');

        parent::tearDown();
    }

    public function test_signature_is_valid_only_for_owned_deployment_and_untampered_values(): void
    {
        $package = PluginPackage::query()->create(['uuid' => '11111111-1111-1111-1111-111111111111']);
        $otherPackage = PluginPackage::query()->create(['uuid' => '22222222-2222-2222-2222-222222222222']);
        $deployment = PluginDeployment::query()->create([
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'plugin_package_id' => $package->id,
        ]);
        $otherDeployment = PluginDeployment::query()->create([
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'plugin_package_id' => $otherPackage->id,
        ]);
        $expires = time() + 300;
        $signature = hash_hmac(
            'sha256',
            $package->uuid.':'.$deployment->uuid.':'.$expires,
            'test-signing-key'
        );
        $service = app(PluginPackageService::class);

        $this->assertTrue($service->verifySignedDownload($package, $deployment->uuid, $expires, $signature));
        $this->assertFalse($service->verifySignedDownload($package, $otherDeployment->uuid, $expires, $signature));
        $this->assertFalse($service->verifySignedDownload($package, $deployment->uuid, $expires, 'tampered'));
        $this->assertFalse($service->verifySignedDownload($package, $deployment->uuid, time() - 1, $signature));
    }

    public function test_url_generation_rejects_mismatched_package_ownership(): void
    {
        $package = PluginPackage::query()->create(['uuid' => '11111111-1111-1111-1111-111111111111']);
        $otherPackage = PluginPackage::query()->create(['uuid' => '22222222-2222-2222-2222-222222222222']);
        $deployment = PluginDeployment::query()->create([
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'plugin_package_id' => $otherPackage->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(PluginPackageService::class)->signedDownloadUrl($package, $deployment);
    }
}
