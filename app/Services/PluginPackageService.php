<?php

namespace App\Services;

use App\Models\Admin\PluginDeployment;
use App\Models\Admin\PluginPackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class PluginPackageService
{
    /**
     * @return array{slug: string, name: string, version: string, description: ?string, author: ?string}
     */
    public function parsePluginZip(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new \InvalidArgumentException('Could not read uploaded file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('The file is not a valid ZIP archive.');
        }

        try {
            $header = $this->extractPluginHeaderFromZip($zip);
        } finally {
            $zip->close();
        }

        if ($header === null) {
            throw new \InvalidArgumentException('No valid WordPress plugin header found in ZIP.');
        }

        return $header;
    }

    public function storeUploadedPackage(UploadedFile $file, int $adminId, ?string $notes = null): PluginPackage
    {
        $maxBytes = max(1, (int) config('plugin_manager.max_zip_mb', 5)) * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new \InvalidArgumentException('ZIP exceeds maximum size of '.config('plugin_manager.max_zip_mb').' MB.');
        }

        $header = $this->parsePluginZip($file);
        $slug = $header['slug'];
        $version = $header['version'];

        if (PluginPackage::query()->where('slug', $slug)->where('version', $version)->exists()) {
            throw new \InvalidArgumentException("Plugin {$slug} version {$version} is already in the library.");
        }

        $uuid = (string) Str::uuid();
        $disk = config('plugin_manager.storage_disk', 'local');
        $storagePath = 'plugin-packages/'.$uuid.'/'.$slug.'-'.$version.'.zip';

        Storage::disk($disk)->putFileAs(
            'plugin-packages/'.$uuid,
            $file,
            $slug.'-'.$version.'.zip'
        );

        $absolutePath = Storage::disk($disk)->path($storagePath);
        $checksum = hash_file('sha256', $absolutePath);

        return PluginPackage::create([
            'uuid' => $uuid,
            'admin_id' => $adminId,
            'slug' => $slug,
            'name' => $header['name'],
            'version' => $version,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $storagePath,
            'file_size_bytes' => (int) $file->getSize(),
            'checksum_sha256' => $checksum,
            'is_diamond_pbn_agent' => $slug === config('plugin_manager.diamond_pbn_slug'),
            'notes' => $notes,
        ]);
    }

    public function deletePackage(PluginPackage $package): void
    {
        $active = PluginDeployment::query()
            ->where('plugin_package_id', $package->id)
            ->whereIn('status', ['queued', 'running', 'processing'])
            ->exists();

        if ($active) {
            throw new \RuntimeException('Cannot delete: an active deployment uses this package.');
        }

        $disk = config('plugin_manager.storage_disk', 'local');
        if (Storage::disk($disk)->exists($package->storage_path)) {
            Storage::disk($disk)->delete($package->storage_path);
        }

        $dir = dirname($package->storage_path);
        if (Storage::disk($disk)->exists($dir)) {
            Storage::disk($disk)->deleteDirectory($dir);
        }

        $package->delete();
    }

    public function signedDownloadUrl(PluginPackage $package, PluginDeployment $deployment): string
    {
        $ttlHours = max(1, (int) config('plugin_manager.download_url_ttl_hours', 24));
        $expires = now()->addHours($ttlHours)->timestamp;

        $payload = $package->uuid.':'.$deployment->uuid.':'.$expires;
        $signature = hash_hmac('sha256', $payload, $this->signingKey());

        return route('plugin-deployments.download', [
            'uuid' => $package->uuid,
            'deployment' => $deployment->uuid,
            'expires' => $expires,
            'signature' => $signature,
        ]);
    }

    public function verifyDownloadSignature(string $packageUuid, string $deploymentUuid, int $expires, string $signature): bool
    {
        if ($expires < time()) {
            return false;
        }

        $expected = hash_hmac('sha256', $packageUuid.':'.$deploymentUuid.':'.$expires, $this->signingKey());

        return hash_equals($expected, $signature);
    }

    public function signingKey(): string
    {
        return (string) (config('plugin_manager.signing_key') ?: config('app.key'));
    }

    /**
     * @return array{slug: string, name: string, version: string, description: ?string, author: ?string}|null
     */
    private function extractPluginHeaderFromZip(ZipArchive $zip): ?array
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! is_array($stat)) {
                continue;
            }

            $filename = $stat['name'];
            if (! preg_match('#^([^/]+)/([^/]+\.php)$#', $filename, $matches)) {
                continue;
            }

            $slug = $matches[1];
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }

            $headers = $this->parsePluginHeaderString($contents);
            if ($headers === null) {
                continue;
            }

            return [
                'slug' => $slug,
                'name' => $headers['name'],
                'version' => $headers['version'],
                'description' => $headers['description'] ?? null,
                'author' => $headers['author'] ?? null,
            ];
        }

        return null;
    }

    /**
     * @return array{name: string, version: string, description?: string, author?: string}|null
     */
    private function parsePluginHeaderString(string $contents): ?array
    {
        if (! preg_match('/Plugin Name:\s*(.+)/i', $contents, $nameMatch)) {
            return null;
        }

        if (! preg_match('/Version:\s*(.+)/i', $contents, $versionMatch)) {
            return null;
        }

        $result = [
            'name' => trim($nameMatch[1]),
            'version' => trim($versionMatch[1]),
        ];

        if (preg_match('/Description:\s*(.+)/i', $contents, $descMatch)) {
            $result['description'] = trim($descMatch[1]);
        }

        if (preg_match('/Author:\s*(.+)/i', $contents, $authorMatch)) {
            $result['author'] = trim($authorMatch[1]);
        }

        return $result;
    }
}
