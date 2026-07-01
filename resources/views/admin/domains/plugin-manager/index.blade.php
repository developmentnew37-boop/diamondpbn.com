@extends('admin.layout.layout')

@section('title', 'Plugin Manager')

@push('style')
    @include('admin.domains.plugin-manager.partials.styles')
@endpush

@section('main-content')
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Plugin Manager</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a><span>›</span></div>
                    <div class="breadcrumb-item"><span class="breadcrumb-link">Plugin Manager</span><span>›</span></div>
                    <div class="breadcrumb-item"><span class="breadcrumb-link">Plugin Library</span></div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end items-center gap-2">
                <a href="{{ route('admin.plugin-manager.deploy.create') }}" class="pm-btn pm-btn-muted">
                    <span class="material-symbols-outlined !text-base">rocket_launch</span>
                    Deploy Plugin
                </a>
                <a href="{{ route('admin.plugin-manager.deployments.index') }}" class="pm-btn pm-btn-muted">
                    <span class="material-symbols-outlined !text-base">history</span>
                    History
                </a>
            </div>
        </div>
    </div>

    <div id="pluginManagerAlert" class="w-full hidden !mt-2" role="alert"></div>

    @if (! $uploadLimits['server_ready'])
        <div class="w-full content-card !mt-4 !p-4 bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
            <strong>Upload limit misconfigured on this server.</strong>
            App allows {{ $uploadLimits['max_zip_mb'] }} MB ZIPs, but PHP allows
            upload {{ $uploadLimits['php_upload_mb'] }} MB / post {{ $uploadLimits['php_post_mb'] }} MB.
            On the VPS, set <code class="bg-white !px-1 rounded">client_max_body_size {{ $uploadLimits['max_zip_mb'] + 2 }}M;</code> in nginx
            and <code class="bg-white !px-1 rounded">upload_max_filesize</code> / <code class="bg-white !px-1 rounded">post_max_size</code>
            in PHP, then reload nginx and PHP-FPM. See <code class="bg-white !px-1 rounded">deploy/nginx/upload-limits.conf</code>.
        </div>
    @endif

    <div class="w-full content-card !mt-4">
        <div class="!p-4 theme-info-box !mb-4">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">info</span>
                <div class="text-sm text-gray-700">
                    <strong>Upload once</strong> per plugin version, then <strong>deploy many times</strong> by category or manual list.
                    Each package stores a <strong>library slug</strong> (catalog) and <strong>WP folder</strong> (<code>expected_slug</code> from the ZIP).
                    Remote sites need Diamond PBN agent <strong>≥ {{ config('plugin_manager.min_agent_version') }}</strong>.
                    Queue: <code class="bg-white !px-1 rounded text-xs">php artisan queue:work --queue=plugin_deployments</code>
                </div>
            </div>
        </div>

        <form id="pluginUploadForm" class="flex flex-col gap-4 !mb-6 !pb-6 border-b border-gray-200"
            data-upload-url="{{ route('admin.plugin-manager.packages.store') }}"
            data-max-bytes="{{ $uploadLimits['effective_bytes'] }}"
            data-max-mb="{{ $uploadLimits['effective_mb'] }}">
            @csrf
            <h3 class="text-base font-semibold text-gray-800">Upload New Package</h3>
            <div class="flex flex-col gap-2 max-w-xl">
                <label for="plugin_zip" class="text-sm font-medium text-gray-700">Plugin ZIP (max {{ $uploadLimits['effective_mb'] }} MB{{ $uploadLimits['effective_mb'] < $uploadLimits['max_zip_mb'] ? ' — server limit' : '' }})</label>
                <input type="file" name="plugin_zip" id="plugin_zip" accept=".zip,application/zip" required
                    class="bg-gray-100 border border-gray-200 !p-2 text-sm w-full rounded">
                <input type="text" name="notes" placeholder="Optional notes" maxlength="1000"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">
            </div>
            <button type="submit" class="pm-btn w-fit" id="pluginUploadBtn">
                <span class="btn-spinner hidden" id="pluginUploadSpinner"></span>
                <span class="material-symbols-outlined !text-base">upload</span>
                Upload to Library
            </button>
        </form>

        <h3 class="text-base font-semibold text-gray-800 !mb-4">Plugin Library</h3>
        @if ($packages->isEmpty())
            <p class="text-sm text-gray-500">No packages yet. Upload a WordPress plugin ZIP above.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-800 text-white">
                        <tr>
                            <th class="!px-4 !py-3">Plugin</th>
                            <th class="!px-4 !py-3">Library Slug</th>
                            <th class="!px-4 !py-3">WP Folder</th>
                            <th class="!px-4 !py-3">Version</th>
                            <th class="!px-4 !py-3">Checksum</th>
                            <th class="!px-4 !py-3">Size</th>
                            <th class="!px-4 !py-3">Uploaded</th>
                            <th class="!px-4 !py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($packages as $package)
                            <tr class="border-b border-gray-100 hover:bg-gray-50" data-package-uuid="{{ $package->uuid }}">
                                <td class="!px-4 !py-3 font-medium">
                                    {{ $package->name }}
                                    @if ($package->is_diamond_pbn_agent)
                                        <span class="text-xs text-orange-600">(Agent)</span>
                                    @endif
                                </td>
                                <td class="!px-4 !py-3 font-mono text-xs">{{ $package->librarySlug() }}</td>
                                <td class="!px-4 !py-3 font-mono text-xs">{{ $package->expectedSlug() }}</td>
                                <td class="!px-4 !py-3">{{ $package->version }}</td>
                                <td class="!px-4 !py-3 font-mono text-xs" title="{{ $package->checksum_sha256 }}">
                                    {{ substr($package->checksum_sha256, 0, 12) }}…
                                </td>
                                <td class="!px-4 !py-3">{{ number_format($package->file_size_bytes / 1024, 1) }} KB</td>
                                <td class="!px-4 !py-3">{{ $package->created_at->format('M j, Y H:i') }}</td>
                                <td class="!px-4 !py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('admin.plugin-manager.deploy.create', ['package' => $package->uuid]) }}"
                                            class="text-[var(--primary-color)] hover:underline text-xs font-semibold">Deploy</a>
                                        <a href="{{ route('admin.plugin-manager.packages.download', $package->uuid) }}"
                                            class="text-gray-600 hover:underline text-xs">Download</a>
                                        <button type="button" class="text-red-600 hover:underline text-xs package-delete-btn"
                                            data-delete-url="{{ route('admin.plugin-manager.packages.destroy', $package->uuid) }}">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/plugin-manager.js') }}?v={{ @filemtime(public_path('js/plugin-manager.js')) }}"></script>
@endpush
