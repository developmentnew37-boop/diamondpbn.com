@extends('admin.layout.layout')

@section('title', 'Deploy Plugin')

@push('style')
    @include('admin.domains.plugin-manager.partials.styles')
@endpush

@section('main-content')
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Deploy Plugin</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a><span>›</span></div>
                    <div class="breadcrumb-item"><a href="{{ route('admin.plugin-manager.index') }}" class="breadcrumb-link">Plugin Manager</a><span>›</span></div>
                    <div class="breadcrumb-item"><span class="breadcrumb-link">Deploy</span></div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end gap-2">
                <a href="{{ route('admin.plugin-manager.index') }}" class="pm-btn pm-btn-muted">← Library</a>
            </div>
        </div>
    </div>

    <div id="pluginManagerAlert" class="w-full hidden !mt-2" role="alert"></div>

    @if ($packages->isEmpty())
        <div class="w-full content-card !mt-4">
            <p class="text-sm text-gray-600">No packages in library. <a href="{{ route('admin.plugin-manager.index') }}" class="text-[var(--primary-color)] font-semibold">Upload a plugin ZIP</a> first.</p>
        </div>
    @else
        <div class="w-full content-card !mt-4">
            <form id="pluginDeployForm" class="flex flex-col gap-4"
                data-start-url="{{ route('admin.plugin-manager.deploy.start') }}">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label for="operation" class="text-sm font-medium text-gray-700">Operation</label>
                        <select name="operation" id="operation" required
                            class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">
                            @foreach ($operations as $op)
                                <option value="{{ $op }}">{{ ucwords(str_replace('_', ' ', $op)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="plugin_package_uuid" class="text-sm font-medium text-gray-700">Package (from library)</label>
                        <select name="plugin_package_uuid" id="plugin_package_uuid" required
                            class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">
                            @foreach ($packages as $package)
                                <option value="{{ $package->uuid }}" @selected($selectedPackageUuid === $package->uuid)
                                    data-expected-slug="{{ $package->expectedSlug() }}"
                                    data-library-slug="{{ $package->librarySlug() }}"
                                    data-version="{{ $package->version }}"
                                    data-name="{{ $package->name }}">
                                    {{ $package->displayLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div id="packageMetaPanel" class="theme-info-box !p-4 text-sm text-gray-700 hidden">
                    <p><strong>WordPress folder:</strong> <code id="metaExpectedSlug" class="bg-white !px-1 rounded text-xs"></code></p>
                    <p class="!mt-1"><strong>Library slug:</strong> <code id="metaLibrarySlug" class="bg-white !px-1 rounded text-xs"></code></p>
                    <p class="!mt-1"><strong>Version:</strong> <span id="metaVersion"></span></p>
                    <p class="!mt-1 text-xs text-gray-500">Install / update / delete use the WordPress folder + version on each remote site.</p>
                </div>

                <input type="hidden" name="source" id="sourceInput" value="inventory">

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="source-tab" data-source="manual">Manual List</button>
                    <button type="button" class="source-tab active" data-source="inventory">By Category</button>
                </div>

                <div id="panel-manual" class="source-panel">
                    <textarea name="domains_list" id="domains_list" rows="10" placeholder="example.com&#10;another.net"
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded font-mono"></textarea>
                    <p class="text-xs text-gray-500">Max {{ config('plugin_manager.max_domains_per_deployment') }} domains per deployment.</p>
                </div>

                <div id="panel-inventory" class="source-panel active">
                    <div class="flex flex-col gap-2 max-w-md">
                        <label for="domain_category_id" class="text-sm font-medium text-gray-700">Domain Category</label>
                        <select name="domain_category_id" id="domain_category_id"
                            class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">
                            <option value="">Select category...</option>
                            @foreach ($domainCategories as $category)
                                <option value="{{ $category->id }}" data-count="{{ $category->domains_count }}">
                                    {{ $category->name }} ({{ $category->domains_count }} domains)
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="activate_after" value="1" checked class="rounded">
                        Activate after install/update
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="skip_if_same_version" value="1" checked class="rounded">
                        Skip if already on this version
                    </label>
                </div>

                <button type="submit" class="pm-btn w-fit" id="deploySubmitBtn">
                    <span class="btn-spinner hidden" id="deploySubmitSpinner"></span>
                    <span class="material-symbols-outlined !text-base">rocket_launch</span>
                    Start Deployment
                </button>
            </form>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/plugin-manager.js') }}?v={{ @filemtime(public_path('js/plugin-manager.js')) }}"></script>
@endpush
