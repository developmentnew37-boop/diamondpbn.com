@extends('admin.layout.layout')

@section('title', ($mode === 'edit' ? 'Edit' : 'Add').' Rate List')

@push('style')
    @include('admin.local-clients.partials.styles')
@endpush

@section('main-content')
@php
    $pageTitle = $mode === 'edit' ? 'Edit Rate List' : 'Add Rate List';
@endphp

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <h2 class="page-title">{{ $pageTitle }}</h2>
                <div class="breadcrumb flex-wrap !mt-1">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.local-client-rate-lists.index') }}" class="breadcrumb-link">Rate Lists</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">{{ $pageTitle }}</span>
                    </div>
                </div>
            </div>
            <div class="shrink-0">
                <a href="{{ route('admin.local-client-rate-lists.index') }}"
                    class="inline-flex items-center justify-center gap-1.5 !px-4 !py-2.5 text-sm font-medium rounded bg-gray-500 hover:bg-gray-600 text-white transition-colors duration-200">
                    <span class="material-symbols-outlined !text-[18px]">arrow_back</span>
                    Back to Rate Lists
                </a>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-col gap-2">
        @if ($errors->any())
            <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                <span class="font-medium">Please fix the errors below and try again.</span>
            </div>
        @endif
    </div>

    <form method="POST"
        action="{{ $mode === 'edit' ? route('admin.local-client-rate-lists.update', $rateList) : route('admin.local-client-rate-lists.store') }}">
        @csrf
        @if ($mode === 'edit')
            @method('PUT')
        @endif

        <div class="w-full content-card !mt-4">
            <div class="lc-section-heading">
                <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">badge</span>
                <h3>Rate List Details</h3>
            </div>

            @if ($mode === 'edit' && ($clientsCount ?? 0) > 0)
                <p class="lc-help-text !mb-4">
                    This list is used by <strong>{{ $clientsCount }}</strong> client(s). Price changes apply to their future billing only.
                </p>
            @endif

            <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="flex flex-col gap-2">
                    <label for="rate_list_name" class="lc-form-label required">Name</label>
                    <input type="text" name="name" id="rate_list_name" value="{{ old('name', $rateList->name) }}" required
                        placeholder="e.g. Company rates" class="lc-form-input">
                    @error('name')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="is_active" class="lc-form-label">Status</label>
                    <select name="is_active" id="is_active" class="lc-form-input">
                        <option value="1" @selected(old('is_active', $rateList->is_active ?? true))>Active</option>
                        <option value="0" @selected(! old('is_active', $rateList->is_active ?? true))>Inactive</option>
                    </select>
                </div>
                <div class="flex flex-col gap-2 lg:col-span-2">
                    <label for="notes" class="lc-form-label">Notes</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Optional notes"
                        class="lc-form-input resize-y min-h-[46px]">{{ old('notes', $rateList->notes) }}</textarea>
                    @error('notes')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="w-full content-card !mt-4">
            <div class="lc-section-heading">
                <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">payments</span>
                <h3>Price Matrix</h3>
            </div>

            <p class="lc-help-text !mb-4">
                Every domain category must have Post, Sidebar, Hidden links, and Sticky filled.
                New categories are auto-seeded at 0.00. Schedule Post uses Post; Schedule Sidebar uses Sidebar.
                Zero is a valid price; empty cells are not allowed.
            </p>

            <div class="overflow-x-auto w-full max-w-full min-w-0">
                <table class="lc-matrix-table">
                    <thead>
                        <tr>
                            <th>Domain category</th>
                            <th>Post</th>
                            <th>Sidebar</th>
                            <th>Hidden links</th>
                            <th>Sticky</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix as $row)
                            <tr>
                                <td class="font-medium text-gray-800">{{ $row['category_name'] }}</td>
                                @foreach (['post_price', 'sidebar_price', 'hidden_links_price', 'sticky_price'] as $field)
                                    <td>
                                        <input type="number" step="0.01" min="0" required
                                            name="prices[{{ $row['domain_category_id'] }}][{{ $field }}]"
                                            value="{{ old('prices.'.$row['domain_category_id'].'.'.$field, $row[$field] ?? '0.00') }}"
                                            placeholder="0.00"
                                            class="lc-form-input">
                                        @error('prices.'.$row['domain_category_id'].'.'.$field)
                                            <div class="text-xs text-red-600 !mt-1">{{ $message }}</div>
                                        @enderror
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        @if ($matrix->isEmpty())
                            <tr>
                                <td colspan="5" class="!py-6 text-center text-gray-500">
                                    No domain categories yet. Add a domain category first.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="w-full flex flex-col sm:flex-row gap-3 justify-end !mt-4 !pb-2">
            <a href="{{ route('admin.local-client-rate-lists.index') }}" class="lc-btn-secondary">
                <span class="material-symbols-outlined !text-base">close</span>
                Cancel
            </a>
            <button type="submit" class="lc-theme-btn">
                <span class="material-symbols-outlined !text-base">save</span>
                {{ $mode === 'edit' ? 'Update Rate List' : 'Save Rate List' }}
            </button>
        </div>
    </form>
@endsection
