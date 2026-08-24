@extends('admin.layout.layout')

@section('title', 'Rate Lists')

@push('style')
    @include('admin.local-clients.partials.styles')
@endpush

@section('main-content')

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="w-full sm:w-auto flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Rate Lists</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.local-clients.index') }}" class="breadcrumb-link">Local Clients</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Rate Lists</span>
                    </div>
                </div>
            </div>
            <div class="w-full sm:w-auto shrink-0">
                <a href="{{ route('admin.local-client-rate-lists.create') }}" class="lc-theme-btn w-full sm:w-auto">
                    <span class="material-symbols-outlined !text-base">add</span>
                    Add Rate List
                </a>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-col gap-2">
        @if (session()->has('cus__success'))
            <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                <span class="font-medium">{{ session('cus__success') }}</span>
            </div>
        @endif
        @if (session()->has('cus__error'))
            <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                <span class="font-medium">{{ session('cus__error') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                @foreach ($errors->all() as $error)
                    <div class="font-medium">{{ $error }}</div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="w-full content-card min-w-0 !mt-4">
        <div class="lc-section-heading !mb-5">
            <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">price_change</span>
            <h3>Shared Rate Lists</h3>
        </div>

        <div class="w-full flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <p class="lc-help-text lg:max-w-lg">
                Shared price packs for clients. Edits apply to future billing only.
            </p>
            <form method="GET" action="{{ url()->current() }}"
                class="w-full lg:w-auto flex flex-col sm:flex-row gap-2 sm:items-center">
                <div class="relative w-full sm:w-[260px]">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search rate lists..."
                        class="lc-form-input !pr-10">
                </div>
                <select name="status" class="lc-form-input sm:w-[150px]">
                    <option value="">All statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
                <button type="submit" class="lc-theme-btn !min-h-[46px] shrink-0">
                    <span class="material-symbols-outlined !text-base">filter_alt</span>
                    Filter
                </button>
                @if (request()->filled('search') || request()->filled('status'))
                    <a href="{{ route('admin.local-client-rate-lists.index') }}"
                        class="lc-btn-secondary !min-h-[46px] shrink-0">
                        <span class="material-symbols-outlined !text-base">filter_alt_off</span>
                        Clear
                    </a>
                @endif
            </form>
        </div>

        <div class="lc-desktop-table overflow-x-auto !mt-6 w-full max-w-full min-w-0">
            <table class="w-full min-w-[640px] border border-gray-200 border-collapse text-sm">
                <thead>
                    <tr class="bg-[var(--sidebar-bg)] text-white">
                        @foreach (['#', 'Name', 'Clients', 'Status', 'Actions'] as $heading)
                            <th class="border border-gray-200 font-sans !font-normal !px-3 !py-3 text-left">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rateLists as $index => $list)
                        <tr class="hover:bg-gray-50 border-b border-gray-100">
                            <td class="border border-gray-200 !px-3 !py-2.5 text-gray-600">
                                {{ $rateLists->firstItem() + $index }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <a href="{{ route('admin.local-client-rate-lists.edit', $list) }}"
                                    class="font-semibold text-gray-900 hover:text-[var(--primary-color)] transition-colors">
                                    {{ $list->name }}
                                </a>
                                @if ($list->notes)
                                    <div class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ $list->notes }}</div>
                                @endif
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <span class="lc-currency-badge">{{ $list->clients_count }}</span>
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <span class="lc-status-badge {{ $list->is_active ? 'active' : 'inactive' }}">
                                    {{ $list->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <div class="flex flex-wrap gap-2 justify-center">
                                    <a href="{{ route('admin.local-client-rate-lists.edit', $list) }}"
                                        class="lc-action-icon-btn bg-yellow-500 hover:bg-yellow-600" title="Edit">
                                        <span class="material-symbols-outlined !text-[16px] text-white">edit_square</span>
                                    </a>
                                    <form method="POST" action="{{ route('admin.local-client-rate-lists.destroy', $list) }}" class="inline"
                                        onsubmit="return confirm({{ json_encode('Delete rate list \"' . $list->name . '\"? Only allowed if no clients are linked.') }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="lc-action-icon-btn bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-red-600"
                                            title="{{ $list->clients_count > 0 ? 'Reassign linked clients before deleting' : 'Delete' }}"
                                            @disabled($list->clients_count > 0)>
                                            <span class="material-symbols-outlined !text-[16px] text-white">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="border border-gray-200">
                                <div class="lc-empty-state">
                                    <div class="material-symbols-outlined">price_change</div>
                                    <p class="font-medium text-gray-700">No rate lists yet</p>
                                    <p class="text-sm !mt-1">Create a shared pack (e.g. Company, Private) to reuse across clients.</p>
                                    <a href="{{ route('admin.local-client-rate-lists.create') }}" class="lc-theme-btn !mt-4 inline-flex">
                                        <span class="material-symbols-outlined !text-base">add</span>
                                        Add Rate List
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lc-mobile-cards !mt-6">
            @forelse ($rateLists as $list)
                <article class="lc-mobile-card">
                    <div class="lc-mobile-card-head">
                        <div>
                            <a href="{{ route('admin.local-client-rate-lists.edit', $list) }}"
                                class="lc-mobile-card-title hover:text-[var(--primary-color)]">
                                {{ $list->name }}
                            </a>
                            @if ($list->notes)
                                <div class="text-xs text-gray-500 !mt-1 line-clamp-2">{{ $list->notes }}</div>
                            @endif
                        </div>
                        <span class="lc-status-badge {{ $list->is_active ? 'active' : 'inactive' }}">
                            {{ $list->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="lc-mobile-card-meta">
                        <div>
                            <label>Clients</label>
                            <span>{{ $list->clients_count }}</span>
                        </div>
                    </div>
                    <div class="lc-mobile-card-actions">
                        <a href="{{ route('admin.local-client-rate-lists.edit', $list) }}" class="lc-chip-btn outline">
                            <span class="material-symbols-outlined">edit_square</span>
                            Edit
                        </a>
                        <form method="POST" action="{{ route('admin.local-client-rate-lists.destroy', $list) }}" class="inline"
                            onsubmit="return confirm({{ json_encode('Delete rate list \"' . $list->name . '\"? Only allowed if no clients are linked.') }});">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="lc-chip-btn danger" @disabled($list->clients_count > 0)>
                                <span class="material-symbols-outlined">delete</span>
                                Delete
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="lc-empty-state">
                    <div class="material-symbols-outlined">price_change</div>
                    <p class="font-medium text-gray-700">No rate lists yet</p>
                </div>
            @endforelse
        </div>

        @if ($rateLists->hasPages())
            <div class="!mt-4">
                {{ $rateLists->links() }}
            </div>
        @endif
    </div>
@endsection
