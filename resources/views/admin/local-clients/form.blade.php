@extends('admin.layout.layout')

@section('title', ($mode === 'edit' ? 'Edit' : 'Add').' Local Client')

@push('style')
    @include('admin.local-clients.partials.styles')
@endpush

@section('main-content')
@php
    $currencySymbol = \App\Support\CurrencyFormatter::symbol($client->default_currency ?? 'USD');
    $pageTitle = $mode === 'edit' ? 'Edit Client' : 'Add Client';
    $backUrl = $mode === 'edit'
        ? route('admin.local-clients.show', $client)
        : route('admin.local-clients.index');
    $backLabel = $mode === 'edit' ? 'Back to client' : 'Back to Local Clients';
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
                        <a href="{{ route('admin.local-clients.index') }}" class="breadcrumb-link">Local Clients</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">{{ $pageTitle }}</span>
                    </div>
                </div>
            </div>
            <div class="shrink-0">
                <a href="{{ $backUrl }}"
                    class="inline-flex items-center justify-center gap-1.5 !px-4 !py-2.5 text-sm font-medium rounded bg-gray-500 hover:bg-gray-600 text-white transition-colors duration-200">
                    <span class="material-symbols-outlined !text-[18px]">arrow_back</span>
                    {{ $backLabel }}
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
        action="{{ $mode === 'edit' ? route('admin.local-clients.update', $client) : route('admin.local-clients.store') }}">
        @csrf
        @if ($mode === 'edit')
            @method('PUT')
        @endif

        {{-- Client details --}}
        <div class="w-full content-card !mt-4">
            <div class="lc-section-heading">
                <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">badge</span>
                <h3>Client Information</h3>
            </div>

            <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="flex flex-col gap-2">
                    <label for="client_name" class="lc-form-label required">Client name</label>
                    <input type="text" name="name" id="client_name" value="{{ old('name', $client->name) }}" required
                        placeholder="e.g. Acme Corp" class="lc-form-input">
                    @error('name')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="company_name" class="lc-form-label">Company</label>
                    <input type="text" name="company_name" id="company_name"
                        value="{{ old('company_name', $client->company_name) }}" placeholder="Company name (optional)"
                        class="lc-form-input">
                    @error('company_name')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="email" class="lc-form-label">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $client->email) }}"
                        placeholder="client@example.com" class="lc-form-input">
                    @error('email')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="phone" class="lc-form-label">Phone</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $client->phone) }}"
                        placeholder="+62 ..." class="lc-form-input">
                    @error('phone')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2 lg:col-span-2">
                    <label for="address" class="lc-form-label">Address</label>
                    <textarea name="address" id="address" rows="2" placeholder="Billing address (optional)"
                        class="lc-form-input resize-y min-h-[46px]">{{ old('address', $client->address) }}</textarea>
                    @error('address')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="default_currency" class="lc-form-label required">Default currency</label>
                    <select name="default_currency" id="default_currency" class="lc-form-input">
                        @foreach ($currencies as $code)
                            <option value="{{ $code }}" @selected(old('default_currency', $client->default_currency ?? 'USD') === $code)>
                                {{ \App\Support\CurrencyFormatter::optionLabel($code) }}
                            </option>
                        @endforeach
                    </select>
                    @error('default_currency')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="is_active" class="lc-form-label">Status</label>
                    <select name="is_active" id="is_active" class="lc-form-input">
                        <option value="1" @selected(old('is_active', $client->is_active ?? true))>Active</option>
                        <option value="0" @selected(! old('is_active', $client->is_active ?? true))>Inactive</option>
                    </select>
                </div>
                <div class="flex flex-col gap-2 lg:col-span-2">
                    <label for="notes" class="lc-form-label">Notes</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Internal notes (optional)"
                        class="lc-form-input resize-y min-h-[46px]">{{ old('notes', $client->notes) }}</textarea>
                    @error('notes')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Price matrix --}}
        <div class="w-full content-card !mt-4">
            <div class="lc-section-heading">
                <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">payments</span>
                <h3>Price Matrix</h3>
            </div>

            <p class="lc-help-text !mb-4">
                Set per-domain-category prices in the client's default currency
                (<strong id="currency-label">{{ $currencySymbol }}</strong>).
                Schedule Post uses the Post column; Schedule Sidebar uses the Sidebar column.
                Leave a cell empty if that category is not billed for this client.
            </p>

            <div class="overflow-x-auto w-full max-w-full min-w-0">
                <table class="lc-matrix-table">
                    <thead>
                        <tr>
                            <th>Domain category</th>
                            <th>Post (<span class="price-symbol">{{ $currencySymbol }}</span>)</th>
                            <th>Sidebar (<span class="price-symbol">{{ $currencySymbol }}</span>)</th>
                            <th>Hidden links (<span class="price-symbol">{{ $currencySymbol }}</span>)</th>
                            <th>Sticky (<span class="price-symbol">{{ $currencySymbol }}</span>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrix as $row)
                            <tr>
                                <td class="font-medium text-gray-800">{{ $row['category_name'] }}</td>
                                @foreach (['post_price', 'sidebar_price', 'hidden_links_price', 'sticky_price'] as $field)
                                    <td>
                                        <input type="number" step="0.01" min="0"
                                            name="prices[{{ $row['domain_category_id'] }}][{{ $field }}]"
                                            value="{{ old('prices.'.$row['domain_category_id'].'.'.$field, $row[$field]) }}"
                                            placeholder="0.00"
                                            class="lc-form-input">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="w-full flex flex-col sm:flex-row gap-3 justify-end !mt-4 !pb-2">
            <a href="{{ route('admin.local-clients.index') }}" class="lc-btn-secondary">
                <span class="material-symbols-outlined !text-base">close</span>
                Cancel
            </a>
            <button type="submit" class="lc-theme-btn">
                <span class="material-symbols-outlined !text-base">save</span>
                {{ $mode === 'edit' ? 'Update Client' : 'Save Client' }}
            </button>
        </div>
    </form>

@push('scripts')
<script>
const currencySymbols = @json(collect($currencies)->mapWithKeys(fn ($c) => [$c => \App\Support\CurrencyFormatter::symbol($c)]));
document.getElementById('default_currency')?.addEventListener('change', function () {
    const sym = currencySymbols[this.value] || this.value;
    document.getElementById('currency-label').textContent = sym;
    document.querySelectorAll('.price-symbol').forEach(el => el.textContent = sym);
});
</script>
@endpush
@endsection
