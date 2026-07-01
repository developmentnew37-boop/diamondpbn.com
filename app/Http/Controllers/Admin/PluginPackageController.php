<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\PluginPackage;
use App\Services\PluginPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PluginPackageController extends Controller
{
    public function __construct(
        private readonly PluginPackageService $packageService
    ) {}

    public function index(): View
    {
        $packages = PluginPackage::query()
            ->orderByDesc('created_at')
            ->get();

        return view('admin.domains.plugin-manager.index', [
            'packages' => $packages,
            'uploadLimits' => pluginManagerUploadLimits(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $limits = pluginManagerUploadLimits();

        if (! $limits['server_ready']) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Server upload limit is too low (PHP upload: %s MB, post: %s MB). Increase nginx client_max_body_size and PHP upload_max_filesize/post_max_size to at least %s MB, then reload nginx/PHP-FPM.',
                    $limits['php_upload_mb'],
                    $limits['php_post_mb'],
                    $limits['max_zip_mb']
                ),
            ], 422);
        }

        $request->validate([
            'plugin_zip' => 'required|file|mimes:zip|max:'.$limits['validation_kb'],
            'notes' => 'nullable|string|max:1000',
        ]);

        $adminId = (int) Auth::guard('admin')->id();

        try {
            $package = $this->packageService->storeUploadedPackage(
                $request->file('plugin_zip'),
                $adminId,
                $request->input('notes')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Upload failed: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Plugin package uploaded to library.',
            'package' => [
                'uuid' => $package->uuid,
                'name' => $package->name,
                'slug' => $package->librarySlug(),
                'expected_slug' => $package->expectedSlug(),
                'version' => $package->version,
                'checksum_sha256' => $package->checksum_sha256,
            ],
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $package = PluginPackage::query()->where('uuid', $uuid)->firstOrFail();

        try {
            $this->packageService->deletePackage($package);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Package removed from library.']);
    }

    public function adminDownload(string $uuid): StreamedResponse
    {
        $package = PluginPackage::query()->where('uuid', $uuid)->firstOrFail();
        $disk = config('plugin_manager.storage_disk', 'local');

        if (! Storage::disk($disk)->exists($package->storage_path)) {
            abort(404, 'Package file not found.');
        }

        return Storage::disk($disk)->download(
            $package->storage_path,
            $package->original_filename
        );
    }

    public function download(Request $request, string $uuid): StreamedResponse
    {
        $request->validate([
            'deployment' => 'required|string',
            'expires' => 'required|integer',
            'signature' => 'required|string',
        ]);

        if (! $this->packageService->verifyDownloadSignature(
            $uuid,
            $request->input('deployment'),
            (int) $request->input('expires'),
            $request->input('signature')
        )) {
            abort(403, 'Invalid or expired download link.');
        }

        $package = PluginPackage::query()->where('uuid', $uuid)->firstOrFail();
        $disk = config('plugin_manager.storage_disk', 'local');

        if (! Storage::disk($disk)->exists($package->storage_path)) {
            abort(404, 'Package file not found.');
        }

        return Storage::disk($disk)->download(
            $package->storage_path,
            $package->expectedSlug().'-'.$package->version.'.zip'
        );
    }
}
