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
        bool $activate,
        ?string $pluginFile = null
    ): array {
        $payload = [
            'delivery' => 'url',
            'download_url' => $downloadUrl,
            'expected_checksum_sha256' => $package->checksum_sha256,
            'slug' => $package->librarySlug(),
            'expected_slug' => $package->expectedSlug(),
            'target_version' => $package->version,
            'activate' => $activate,
        ];

        if ($pluginFile !== null && $pluginFile !== '') {
            $payload['plugin_file'] = $pluginFile;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function activateOrDeactivate(PluginPackage $package, ?string $targetVersion = null): array
    {
        $payload = [
            'slug' => $package->librarySlug(),
            'expected_slug' => $package->expectedSlug(),
        ];

        // Prefer the version actually on the remote site when known; otherwise omit
        // so the agent can match by expected_slug / plugin_file alone.
        $version = $targetVersion !== null && $targetVersion !== ''
            ? $targetVersion
            : null;

        if ($version !== null) {
            $payload['target_version'] = $version;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(PluginPackage $package, ?string $pluginFile = null, ?string $targetVersion = null): array
    {
        $payload = $this->activateOrDeactivate($package, $targetVersion);

        if ($pluginFile !== null && $pluginFile !== '') {
            $payload['plugin_file'] = $pluginFile;
        }

        return $payload;
    }
}
