<?php

namespace App\Services;

use App\Models\Admin\PluginPackage;

class PluginDeployPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function installOrUpdate(
        PluginPackage $package,
        string $downloadUrl,
        bool $activate
    ): array {
        return [
            'delivery' => 'url',
            'download_url' => $downloadUrl,
            'expected_checksum_sha256' => $package->checksum_sha256,
            'slug' => $package->librarySlug(),
            'expected_slug' => $package->expectedSlug(),
            'target_version' => $package->version,
            'activate' => $activate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activateOrDeactivate(PluginPackage $package): array
    {
        return [
            'slug' => $package->librarySlug(),
            'expected_slug' => $package->expectedSlug(),
            'target_version' => $package->version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(PluginPackage $package, ?string $pluginFile = null): array
    {
        $payload = $this->activateOrDeactivate($package);

        if ($pluginFile !== null && $pluginFile !== '') {
            $payload['plugin_file'] = $pluginFile;
        }

        return $payload;
    }
}
