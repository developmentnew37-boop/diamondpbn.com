@extends('admin.layout.layout')

@section('title', 'Deploy Plugin')

@push('style')
    @include('admin.domains.plugin-manager.partials.styles')
@endpush

@section('main-content')
    <div class="page-header pm-page-header">
        <div class="pm-page-heading">
            <h2 class="page-title">Deploy Plugin</h2>
            <div class="breadcrumb flex-wrap">
                <div class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a><span>›</span></div>
                <div class="breadcrumb-item"><a href="{{ route('admin.plugin-manager.index') }}" class="breadcrumb-link">Plugin Manager</a><span>›</span></div>
                <div class="breadcrumb-item"><span class="breadcrumb-link">Deploy</span></div>
            </div>
        </div>
        <div class="pm-page-actions">
                <a href="{{ route('admin.plugin-manager.index') }}" class="pm-btn pm-btn-muted">← Library</a>
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

                <div class="flex flex-col gap-2 max-w-md">
                    <label for="operation" class="text-sm font-medium text-gray-700">Operation</label>
                    <select name="operation" id="operation" required
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">
                        @foreach ($operations as $op)
                            <option value="{{ $op }}">{{ ucwords(str_replace('_', ' ', $op)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-2" id="pluginPackagePicker" data-max-plugins="{{ $maxPlugins }}">
                    <label class="text-sm font-medium text-gray-700" for="pluginPackageSearch">Plugins</label>
                    <p class="text-xs text-gray-500">Select up to {{ $maxPlugins }}. Each plugin gets its own deployment, and the next one starts when the current one finishes.</p>
                    <input type="search" id="pluginPackageSearch" class="pm-package-search" placeholder="Search by name, version, or folder" autocomplete="off">
                    <div class="pm-package-picker">
                        @foreach ($packages as $package)
                            <label class="pm-package-option" title="{{ $package->name }}">
                                <input type="checkbox" class="pm-package-check" value="{{ $package->uuid }}"
                                    data-name="{{ $package->name }}"
                                    data-version="{{ $package->version }}"
                                    data-expected-slug="{{ $package->expectedSlug() }}"
                                    @checked($selectedPackageUuid === $package->uuid)>
                                <span class="pm-package-order" hidden></span>
                                <span class="pm-package-option-copy">
                                    <span class="pm-package-option-name">{{ $package->name }}</span>
                                    <span class="pm-package-option-meta">v{{ $package->version }}</span>
                                    <span class="pm-package-option-meta">{{ $package->expectedSlug() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p id="pluginPackageEmpty" class="pm-package-empty hidden">No plugins match that search.</p>
                    <div id="pluginRunOrder" class="pm-run-order hidden">
                        <p class="pm-run-order-title">Run order</p>
                        <ol id="pluginRunOrderList" class="pm-run-order-list"></ol>
                    </div>
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
