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
        return $this->findByFolder($inventory, $expectedSlug) !== null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventory
     * @return array{slug: string, version: ?string, plugin_file: ?string, active: ?bool}|null
     */
    public function findExactVersionMatch(array $inventory, PluginPackage $package): ?array
    {
        $expectedSlug = $package->expectedSlug();

        foreach ($inventory as $plugin) {
            if (! is_array($plugin) || ! $this->pluginMatchesFolder($plugin, $expectedSlug)) {
                continue;
            }

            $remoteVersion = isset($plugin['version']) ? (string) $plugin['version'] : null;
            if ($remoteVersion !== null && version_compare($remoteVersion, $package->version, '==')) {
                return $this->toMatch($plugin, $expectedSlug);
            }
        }

        return $this->findUniqueByNameAndVersion($inventory, $package, true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventory
     * @return array{slug: string, version: ?string, plugin_file: ?string, active: ?bool}|null
     */
    public function findFolderMatch(array $inventory, PluginPackage $package): ?array
    {
        return $this->findByFolder($inventory, $package->expectedSlug())
            ?? $this->findUniqueByNameAndVersion($inventory, $package, false);
    }

    /**
     * Installed copy for delete/activate/deactivate: folder first, then unique name+version.
     *
     * @param  array<int, array<string, mixed>>  $inventory
     * @return array{slug: string, version: ?string, plugin_file: ?string, active: ?bool}|null
     */
    public function findInstalledMatch(array $inventory, PluginPackage $package): ?array
    {
        return $this->findExactVersionMatch($inventory, $package)
            ?? $this->findByFolder($inventory, $package->expectedSlug())
            ?? $this->findUniqueByNameAndVersion($inventory, $package, false);
    }

    /**
     * Short list of remote folder names for skip diagnostics.
     *
     * @param  array<int, array<string, mixed>>  $inventory
     * @return list<string>
     */
    public function inventoryFolderLabels(array $inventory, int $limit = 8): array
    {
        $labels = [];
        foreach ($inventory as $plugin) {
            if (! is_array($plugin)) {
                continue;
            }
            $slug = $this->remoteFolderSlug($plugin);
            if ($slug === null || $slug === '') {
                continue;
            }
            $labels[$slug] = $slug;
            if (count($labels) >= $limit) {
                break;
            }
        }

        return array_values($labels);
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventory
     */
    public function resolveOperation(string $intent, array $inventory, PluginPackage $package): string
    {
        $exactMatch = $this->findExactVersionMatch($inventory, $package);
        $folderMatch = $this->findByFolder($inventory, $package->expectedSlug());
        $nameMatch = $this->findUniqueByNameAndVersion($inventory, $package, false);
        $installed = $exactMatch !== null || $folderMatch !== null || $nameMatch !== null;

        return match ($intent) {
            'install', 'update', 'update_if_older' => $exactMatch !== null
                ? 'skip'
                : (($folderMatch !== null || $nameMatch !== null) ? 'update' : 'install'),
            'delete' => $installed ? 'delete' : 'skip',
            'activate', 'deactivate' => $installed ? $intent : 'skip',
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

    /**
     * When only one remote candidate exists, use it (delete/activate/deactivate).
     *
     * @param  array<int, array<string, mixed>>  $candidates
     */
    public function pickSingleCandidate(array $candidates): ?array
    {
        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inventory
     * @return array{slug: string, version: ?string, plugin_file: ?string, active: ?bool}|null
     */
    private function findByFolder(array $inventory, string $expectedSlug): ?array
    {
        foreach ($inventory as $plugin) {
            if (! is_array($plugin) || ! $this->pluginMatchesFolder($plugin, $expectedSlug)) {
                continue;
            }

            return $this->toMatch($plugin, $expectedSlug);
        }

        return null;
    }

    /**
     * Match by Plugin Name + version when folder slug differs (common when WP text-domain ≠ folder).
     *
     * @param  array<int, array<string, mixed>>  $inventory
     * @return array{slug: string, version: ?string, plugin_file: ?string, active: ?bool}|null
     */
    private function findUniqueByNameAndVersion(
        array $inventory,
        PluginPackage $package,
        bool $requireSameVersion
    ): ?array {
        $packageName = trim((string) $package->name);
        if ($packageName === '') {
            return null;
        }

        $matches = [];
        foreach ($inventory as $plugin) {
            if (! is_array($plugin)) {
                continue;
            }

            $remoteName = $this->pluginName($plugin);
            if ($remoteName === null || ! $this->namesMatch($packageName, $remoteName)) {
                continue;
            }

            $remoteVersion = isset($plugin['version']) ? (string) $plugin['version'] : null;
            if ($requireSameVersion) {
                if ($remoteVersion === null || version_compare($remoteVersion, $package->version, '!=')) {
                    continue;
                }
            }

            $matches[] = $this->toMatch($plugin, $package->expectedSlug());
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @param  array<string, mixed>  $plugin
     */
    private function pluginMatchesFolder(array $plugin, string $expectedSlug): bool
    {
        $folder = $this->remoteFolderSlug($plugin);
        if ($folder !== null && $this->foldersMatch($expectedSlug, $folder)) {
            return true;
        }

        // Inventory "slug" is sometimes the text domain, while plugin_file holds the real folder.
        $declaredSlug = isset($plugin['slug']) ? trim((string) $plugin['slug']) : '';
        if ($declaredSlug !== '' && $this->foldersMatch($expectedSlug, $declaredSlug)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $plugin
     */
    private function remoteFolderSlug(array $plugin): ?string
    {
        $fromFile = $this->folderFromPluginFile($plugin['plugin_file'] ?? null);
        if ($fromFile !== null) {
            return $fromFile;
        }

        $slug = isset($plugin['slug']) ? trim((string) $plugin['slug']) : '';

        return $slug !== '' ? $slug : null;
    }

    private function folderFromPluginFile(mixed $pluginFile): ?string
    {
        if (! is_string($pluginFile) || $pluginFile === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $pluginFile);
        $parts = explode('/', $normalized);

        return ($parts[0] ?? '') !== '' ? $parts[0] : null;
    }

    /**
     * @param  array<string, mixed>  $plugin
     */
    private function pluginName(array $plugin): ?string
    {
        foreach (['name', 'Name', 'plugin_name', 'title', 'Title'] as $key) {
            if (! empty($plugin[$key]) && is_string($plugin[$key])) {
                return trim($plugin[$key]);
            }
        }

        return null;
    }

    private function foldersMatch(string $expected, string $remote): bool
    {
        $left = $this->normalizeFolderToken($expected);
        $right = $this->normalizeFolderToken($remote);

        return $left !== '' && $left === $right;
    }

    private function normalizeFolderToken(string $value): string
    {
        $value = strtolower(trim($value));

        return str_replace('_', '-', $value);
    }

    private function namesMatch(string $a, string $b): bool
    {
        return strcasecmp(trim($a), trim($b)) === 0;
    }

    /**
     * @param  array<string, mixed>  $plugin
     * @return array{slug: string, version: ?string, plugin_file: ?string, active: ?bool}
     */
    private function toMatch(array $plugin, string $fallbackSlug): array
    {
        return [
            'slug' => $this->remoteFolderSlug($plugin) ?? $fallbackSlug,
            'version' => isset($plugin['version']) ? (string) $plugin['version'] : null,
            'plugin_file' => isset($plugin['plugin_file']) ? (string) $plugin['plugin_file'] : null,
            'active' => isset($plugin['active']) ? (bool) $plugin['active'] : null,
        ];
    }
}
