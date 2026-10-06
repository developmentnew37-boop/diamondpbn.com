@extends('admin.layout.layout')

@section('title', 'Plugin Manager')

@push('style')
    @include('admin.domains.plugin-manager.partials.styles')
@endpush

@section('main-content')
    <div class="page-header pm-page-header">
        <div class="pm-page-heading">
            <h2 class="page-title">Plugin Manager</h2>
            <div class="breadcrumb flex-wrap">
                <div class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a><span>›</span></div>
                <div class="breadcrumb-item"><span class="breadcrumb-link">Plugin Manager</span><span>›</span></div>
                <div class="breadcrumb-item"><span class="breadcrumb-link">Plugin Library</span></div>
            </div>
        </div>
        <div class="pm-page-actions">
                <a href="{{ route('admin.plugin-manager.reports.create') }}" class="pm-btn pm-btn-muted">
                    <span class="material-symbols-outlined !text-base">description</span>
                    Plugin Report
                </a>
                <a href="{{ route('admin.plugin-manager.deployments.index') }}" class="pm-btn pm-btn-muted">
                    <span class="material-symbols-outlined !text-base">history</span>
                    History
                </a>
                <a href="{{ route('admin.plugin-manager.deploy.create') }}" class="pm-btn">
                    <span class="material-symbols-outlined !text-base">rocket_launch</span>
                    Deploy Plugin
                </a>
            </div>
    </div>

    <div id="pluginManagerAlert" class="w-full hidden !mt-2" role="alert"></div>

    @if (! $uploadLimits['server_ready'])
        <div class="w-full content-card !mt-4 !p-4 bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm" role="alert">
            <strong>Server upload limit is below the dashboard ceiling.</strong>
            App allows {{ $uploadLimits['max_zip_mb'] }} MB ZIPs, but PHP allows
            upload {{ $uploadLimits['php_upload_mb'] }} MB / post {{ $uploadLimits['php_post_mb'] }} MB.
            ZIPs up to {{ $uploadLimits['effective_mb'] }} MB can still be uploaded now.
            On the VPS, set <code class="bg-white !px-1 rounded">client_max_body_size {{ $uploadLimits['max_zip_mb'] + 2 }}M;</code> in nginx
            and <code class="bg-white !px-1 rounded">upload_max_filesize</code> / <code class="bg-white !px-1 rounded">post_max_size</code>
            in PHP, then reload nginx and PHP-FPM. See <code class="bg-white !px-1 rounded">deploy/nginx/upload-limits.conf</code>.
        </div>
    @endif

    <div class="w-full content-card !mt-4 pm-library-card">
        <div class="pm-library-note">
            <span class="material-symbols-outlined pm-library-note-icon">info</span>
            <div class="pm-library-note-body">
                <p class="pm-library-note-title">Upload once, deploy many times</p>
                <p class="pm-library-note-copy">
                    Each package stores a library slug and the WordPress folder from the ZIP.
                    Remote sites need Diamond PBN agent <strong>{{ config('plugin_manager.min_agent_version') }}</strong> or newer.
                </p>
            </div>
        </div>

        <form id="pluginUploadForm" class="pm-upload-form"
            data-upload-url="{{ route('admin.plugin-manager.packages.store') }}"
            data-max-bytes="{{ $uploadLimits['effective_bytes'] }}"
            data-max-mb="{{ $uploadLimits['effective_mb'] }}">
            @csrf
            <div class="pm-upload-copy">
                <h3 class="text-base font-semibold text-gray-800">Upload New Package</h3>
                <p class="text-sm text-gray-500">ZIP only, up to {{ $uploadLimits['effective_mb'] }} MB{{ $uploadLimits['effective_mb'] < $uploadLimits['max_zip_mb'] ? ' on this server' : '' }}.</p>
            </div>
            <div class="pm-upload-fields">
                <label for="plugin_zip" class="pm-filter-label">Plugin ZIP</label>
                <input type="file" name="plugin_zip" id="plugin_zip" accept=".zip,application/zip" required class="pm-file-input">
                <input type="text" name="notes" placeholder="Optional notes" maxlength="1000" class="pm-filter-control">
            </div>
            <button type="submit" class="pm-btn w-fit" id="pluginUploadBtn">
                <span class="btn-spinner hidden" id="pluginUploadSpinner"></span>
                <span class="material-symbols-outlined !text-base">upload</span>
                Upload to Library
            </button>
        </form>

        <div class="pm-library-heading">
            <h3 class="text-base font-semibold text-gray-800">Plugin Library</h3>
            @if ($packages->isNotEmpty())
                <span class="pm-library-count">{{ $packages->count() }} {{ \Illuminate\Support\Str::plural('package', $packages->count()) }}</span>
            @endif
        </div>
        @if ($packages->isEmpty())
            <p class="text-sm text-gray-500">No packages yet. Upload a WordPress plugin ZIP above.</p>
        @else
            <div class="pm-library-table-wrap">
                <table class="pm-library-table">
                    <thead>
                        <tr>
                            <th class="pm-th-sno">S.No</th>
                            <th>Plugin</th>
                            <th>Library Slug</th>
                            <th>WP Folder</th>
                            <th>Version</th>
                            <th>Checksum</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th class="pm-th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($packages as $package)
                            <tr data-package-uuid="{{ $package->uuid }}">
                                <td class="pm-sno">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="pm-library-name" title="{{ $package->name }}">
                                        <span class="pm-name-text">{{ $package->name }}</span>
                                        @if ($package->is_diamond_pbn_agent)
                                            <span class="pm-agent-tag">Agent</span>
                                        @endif
                                    </div>
                                </td>
                                <td><span class="pm-mono" title="{{ $package->librarySlug() }}">{{ $package->librarySlug() }}</span></td>
                                <td><span class="pm-mono" title="{{ $package->expectedSlug() }}">{{ $package->expectedSlug() }}</span></td>
                                <td><span class="pm-version" title="{{ $package->version }}">{{ $package->version }}</span></td>
                                <td><span class="pm-mono" title="{{ $package->checksum_sha256 }}">{{ substr($package->checksum_sha256, 0, 12) }}…</span></td>
                                <td>{{ number_format($package->file_size_bytes / 1024, 1) }} KB</td>
                                <td class="pm-library-date">{{ $package->created_at->format('M j, Y H:i') }}</td>
                                <td>
                                    <div class="pm-row-actions">
                                        <a href="{{ route('admin.plugin-manager.deploy.create', ['package' => $package->uuid]) }}" class="pm-row-action pm-row-action-primary">
                                            <span class="material-symbols-outlined">rocket_launch</span>
                                            Deploy
                                        </a>
                                        <a href="{{ route('admin.plugin-manager.packages.download', $package->uuid) }}" class="pm-row-action">
                                            <span class="material-symbols-outlined">download</span>
                                            Download
                                        </a>
                                        <button type="button" class="pm-row-action pm-row-action-danger package-delete-btn"
                                            data-delete-url="{{ route('admin.plugin-manager.packages.destroy', $package->uuid) }}">
                                            <span class="material-symbols-outlined">delete</span>
                                            Delete
                                        </button>
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
