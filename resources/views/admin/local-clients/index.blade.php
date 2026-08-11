@extends('admin.layout.layout')

@section('title', 'Local Clients')

@push('style')
    @include('admin.local-clients.partials.styles')
@endpush

@section('main-content')

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="w-full sm:w-auto flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Local Clients</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Addons</span>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Local Clients</span>
                    </div>
                </div>
            </div>
            <div class="w-full sm:w-auto shrink-0">
                <a href="{{ route('admin.local-clients.create') }}" class="lc-theme-btn w-full sm:w-auto">
                    <span class="material-symbols-outlined !text-base">person_add</span>
                    Add Client
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
    </div>

    <div class="w-full content-card min-w-0 !mt-4">
        <div class="lc-section-heading !mb-5">
            <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">groups</span>
            <h3>All Clients</h3>
        </div>

        <div class="w-full flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <p class="lc-help-text lg:max-w-md">Manage client profiles, per-category pricing, billing reports, and payment tracking.</p>
            <form method="GET" action="{{ url()->current() }}"
                class="w-full lg:w-auto flex flex-col sm:flex-row gap-2 sm:items-center">
                <div class="relative w-full sm:w-[260px]">
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search clients..."
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
            </form>
        </div>

        <div class="lc-desktop-table overflow-x-auto !mt-6 w-full max-w-full min-w-0">
            <table class="w-full min-w-[760px] border border-gray-200 border-collapse text-sm">
                <thead>
                    <tr class="bg-[var(--sidebar-bg)] text-white">
                        @foreach (['#', 'Client', 'Company', 'Currency', 'Status', 'Actions'] as $heading)
                            <th class="border border-gray-200 font-sans !font-normal !px-3 !py-3 text-left">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $index => $client)
                        <tr class="hover:bg-gray-50 border-b border-gray-100">
                            <td class="border border-gray-200 !px-3 !py-2.5 text-gray-600">
                                {{ $clients->firstItem() + $index }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <a href="{{ route('admin.local-clients.show', $client) }}"
                                    class="font-semibold text-gray-900 hover:text-[var(--primary-color)] transition-colors">
                                    {{ $client->name }}
                                </a>
                                @if ($client->email)
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $client->email }}</div>
                                @endif
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5 text-gray-700">
                                {{ $client->company_name ?: '—' }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <span class="lc-currency-badge">{{ $client->default_currency }}</span>
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <span class="lc-status-badge {{ $client->is_active ? 'active' : 'inactive' }}">
                                    {{ $client->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <div class="flex flex-wrap gap-2 justify-center">
                                    <a href="{{ route('admin.local-clients.show', $client) }}"
                                        class="lc-action-icon-btn bg-blue-600 hover:bg-blue-700" title="View">
                                        <span class="material-symbols-outlined !text-[16px] text-white">visibility</span>
                                    </a>
                                    <a href="{{ route('admin.local-clients.edit', $client) }}"
                                        class="lc-action-icon-btn bg-yellow-500 hover:bg-yellow-600" title="Edit">
                                        <span class="material-symbols-outlined !text-[16px] text-white">edit_square</span>
                                    </a>
                                    <form method="POST" action="{{ route('admin.local-clients.destroy', $client) }}" class="inline"
                                        onsubmit="return confirm({{ json_encode('Delete client ' . $client->name . '? This removes pricing, billing periods, payment history, and unlinks all campaigns. This cannot be undone.') }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="lc-action-icon-btn bg-red-600 hover:bg-red-700" title="Delete">
                                            <span class="material-symbols-outlined !text-[16px] text-white">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="border border-gray-200">
                                <div class="lc-empty-state">
                                    <div class="material-symbols-outlined">group_off</div>
                                    <p class="font-medium text-gray-700">No clients yet</p>
                                    <p class="text-sm !mt-1">Add your first local client to start billing campaigns.</p>
                                    <a href="{{ route('admin.local-clients.create') }}" class="lc-theme-btn !mt-4 inline-flex">
                                        <span class="material-symbols-outlined !text-base">person_add</span>
                                        Add Client
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lc-mobile-cards !mt-6">
            @forelse ($clients as $index => $client)
                <article class="lc-mobile-card">
                    <div class="lc-mobile-card-head">
                        <div>
                            <a href="{{ route('admin.local-clients.show', $client) }}"
                                class="lc-mobile-card-title hover:text-[var(--primary-color)]">
                                {{ $client->name }}
                            </a>
                            @if ($client->email)
                                <div class="text-xs text-gray-500 !mt-1">{{ $client->email }}</div>
                            @endif
                        </div>
                        <span class="lc-status-badge {{ $client->is_active ? 'active' : 'inactive' }}">
                            {{ $client->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="lc-mobile-card-meta">
                        <div>
                            <label>Company</label>
                            <span>{{ $client->company_name ?: '—' }}</span>
                        </div>
                        <div>
                            <label>Currency</label>
                            <span>{{ $client->default_currency }}</span>
                        </div>
                    </div>
                    <div class="lc-mobile-card-actions">
                        <a href="{{ route('admin.local-clients.show', $client) }}" class="lc-chip-btn primary">
                            <span class="material-symbols-outlined">visibility</span>
                            View
                        </a>
                        <a href="{{ route('admin.local-clients.edit', $client) }}" class="lc-chip-btn outline">
                            <span class="material-symbols-outlined">edit_square</span>
                            Edit
                        </a>
                        <form method="POST" action="{{ route('admin.local-clients.destroy', $client) }}" class="inline"
                            onsubmit="return confirm({{ json_encode('Delete client ' . $client->name . '? This removes pricing, billing periods, payment history, and unlinks all campaigns. This cannot be undone.') }});">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="lc-chip-btn danger">
                                <span class="material-symbols-outlined">delete</span>
                                Delete
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="lc-empty-state">
                    <div class="material-symbols-outlined">group_off</div>
                    <p class="font-medium text-gray-700">No clients yet</p>
                </div>
            @endforelse
        </div>

        @if ($clients->hasPages())
            <div class="!mt-4">
                {{ $clients->links() }}
            </div>
        @endif
    </div>

@endsection
