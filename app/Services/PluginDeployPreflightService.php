<?php

namespace App\Services;

use App\Models\Admin\PluginPackage;

class PluginDeployPreflightService
{
    /**
     * @param  array<int, array<string, mixed>>  $inventory
     */
    public function hasFolder(array $inventory, string $expectedSlug): bool
    {
        foreach ($inventory as $plugin) {
            if (($plugin['slug'] ?? '') === $expectedSlug) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventory
     * @return array{slug: string, version: ?string, plugin_file: ?string, active: ?bool}|null
     */
    public function findExactVersionMatch(array $inventory, PluginPackage $package): ?array
    {
        $expectedSlug = $package->expectedSlug();

        foreach ($inventory as $plugin) {
            if (($plugin['slug'] ?? '') !== $expectedSlug) {
                continue;
            }

            $remoteVersion = isset($plugin['version']) ? (string) $plugin['version'] : null;
            if ($remoteVersion !== null && version_compare($remoteVersion, $package->version, '==')) {
                return [
                    'slug' => $expectedSlug,
                    'version' => $remoteVersion,
                    'plugin_file' => isset($plugin['plugin_file']) ? (string) $plugin['plugin_file'] : null,
                    'active' => isset($plugin['active']) ? (bool) $plugin['active'] : null,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventory
     * @return array{slug: string, version: ?string, plugin_file: ?string, active: ?bool}|null
     */
    public function findFolderMatch(array $inventory, PluginPackage $package): ?array
    {
        $expectedSlug = $package->expectedSlug();

        foreach ($inventory as $plugin) {
            if (($plugin['slug'] ?? '') === $expectedSlug) {
                return [
                    'slug' => $expectedSlug,
                    'version' => isset($plugin['version']) ? (string) $plugin['version'] : null,
                    'plugin_file' => isset($plugin['plugin_file']) ? (string) $plugin['plugin_file'] : null,
                    'active' => isset($plugin['active']) ? (bool) $plugin['active'] : null,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventory
     */
    public function resolveOperation(string $intent, array $inventory, PluginPackage $package): string
    {
        $exactMatch = $this->findExactVersionMatch($inventory, $package);
        $hasFolder = $this->hasFolder($inventory, $package->expectedSlug());

        return match ($intent) {
            'install', 'update', 'update_if_older' => $exactMatch !== null
                ? 'skip'
                : ($hasFolder ? 'update' : 'install'),
            'delete' => ($exactMatch !== null || $hasFolder) ? 'delete' : 'skip',
            'activate', 'deactivate' => $hasFolder ? $intent : 'skip',
            default => throw new \InvalidArgumentException('Invalid deployment operation.'),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    public function pickCandidateForVersion(array $candidates, string $targetVersion): ?array
    {
        $matches = array_values(array_filter($candidates, function ($candidate) use ($targetVersion) {
            $version = isset($candidate['version']) ? (string) $candidate['version'] : null;

            return $version !== null && version_compare($version, $targetVersion, '==');
        }));

        if (count($matches) === 1) {
            return $matches[0];
        }

        return null;
    }
}
