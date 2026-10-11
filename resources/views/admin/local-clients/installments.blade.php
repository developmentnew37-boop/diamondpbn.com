@extends('admin.layout.layout')

@section('title', 'Installments')

@push('style')
    @include('admin.local-clients.partials.styles')
    <style>
        .lc-installment-scroll {
            display: block;
            overflow-x: auto;
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
        }
        .lc-installment-scroll table {
            width: max-content;
            min-width: 100%;
        }
        .lc-installment-scroll th,
        .lc-installment-scroll td {
            white-space: nowrap;
        }
        @media (max-width: 767px) {
            .lc-installment-scroll.lc-desktop-table {
                display: block;
            }
            .lc-installment-ledger .lc-mobile-cards {
                display: none;
            }
        }
    </style>
@endpush

@section('main-content')
    @php
        $exportQuery = request()->only(['client_id', 'admin_id', 'from', 'to']);
    @endphp

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="w-full sm:w-auto flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Installments</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Local Clients</span>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Installments</span>
                    </div>
                </div>
            </div>
            <div class="w-full sm:w-auto shrink-0 flex flex-col sm:flex-row gap-2">
                <a href="{{ route('admin.local-clients.installments.export', array_merge($exportQuery, ['format' => 'xlsx'])) }}" class="lc-theme-btn w-full sm:w-auto !min-h-[42px]">
                    <span class="material-symbols-outlined !text-base">download</span>
                    Export Excel
                </a>
                <a href="{{ route('admin.local-clients.installments.export', array_merge($exportQuery, ['format' => 'pdf'])) }}" class="lc-btn-secondary w-full sm:w-auto !min-h-[42px]">
                    <span class="material-symbols-outlined !text-base">picture_as_pdf</span>
                    Export PDF
                </a>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-col gap-2 !mt-4">
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
        <form method="GET" action="{{ route('admin.local-clients.installments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="installment-client" class="lc-modal-label">Client</label>
                <select id="installment-client" name="client_id" class="lc-form-input">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="installment-admin" class="lc-modal-label">Recorded by</label>
                <select id="installment-admin" name="admin_id" class="lc-form-input">
                    <option value="">Everyone</option>
                    @foreach ($admins as $admin)
                        <option value="{{ $admin->id }}" @selected((string) ($filters['admin_id'] ?? '') === (string) $admin->id)>{{ $admin->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="installment-from" class="lc-modal-label">From</label>
                <input id="installment-from" type="date" name="from" value="{{ $filters['from'] }}" class="lc-form-input">
            </div>
            <div>
                <label for="installment-to" class="lc-modal-label">To</label>
                <input id="installment-to" type="date" name="to" value="{{ $filters['to'] }}" class="lc-form-input">
            </div>
            <div class="flex items-end">
                <button type="submit" class="lc-theme-btn w-full !min-h-[46px]">
                    <span class="material-symbols-outlined !text-base">filter_alt</span>
                    Filter
                </button>
            </div>
        </form>
    </div>

    @if ($accountSummary)
        <div class="w-full grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 !mt-4">
            <div class="lc-stat-card">
                <div class="lc-stat-label">Still to pay</div>
                <div class="lc-stat-value unpaid">{{ $accountSummary['still_to_pay'] }}</div>
            </div>
            <div class="lc-stat-card">
                <div class="lc-stat-label">Installments received</div>
                <div class="lc-stat-value paid">{{ $accountSummary['received'] }}</div>
            </div>
            <div class="lc-stat-card">
                <div class="lc-stat-label">This month billed ({{ $accountSummary['month_label'] }})</div>
                <div class="lc-stat-value">{{ $accountSummary['month_billed'] }}</div>
            </div>
            <div class="lc-stat-card">
                <div class="lc-stat-label">This month received ({{ $accountSummary['month_label'] }})</div>
                <div class="lc-stat-value paid">{{ $accountSummary['month_received'] }}</div>
            </div>
        </div>
    @else
        <p class="lc-help-text !mt-4">Choose a client to see what they still owe and what was received.</p>
    @endif

    <div class="lc-installment-ledger w-full content-card min-w-0 !mt-4">
        <div class="lc-section-heading !mb-2">
            <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">payments</span>
            <h3>Installment ledger</h3>
        </div>
        <p class="lc-help-text !mb-5">Revert is available on the newest payment for each client. Older payments can be reverted after that one is undone.</p>

        <div class="lc-desktop-table lc-installment-scroll w-full max-w-full min-w-0">
            <table class="border border-gray-200 border-collapse text-sm">
                <thead>
                    <tr class="bg-[var(--sidebar-bg)] text-white">
                        @foreach (['S.No', 'Date', 'Client', 'Amount', 'Recorded by', 'Type', 'Note', 'Campaigns', 'Action'] as $heading)
                            <th class="border border-gray-200 font-sans !font-normal !px-3 !py-3 text-left">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($installments as $index => $installment)
                        <tr class="hover:bg-gray-50 border-b border-gray-100">
                            <td class="border border-gray-200 !px-3 !py-2.5 text-gray-600">
                                {{ $installments->firstItem() + $index }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5 whitespace-nowrap text-gray-700">
                                {{ $installment->created_at?->format('M d, Y g:i A') }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                @if ($installment->localClient)
                                    <a href="{{ route('admin.local-clients.show', $installment->localClient) }}" class="font-semibold text-gray-900 hover:text-[var(--primary-color)]">
                                        {{ $installment->localClient->name }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5 whitespace-nowrap font-semibold tabular-nums">
                                {{ \App\Support\CurrencyFormatter::format($installment->amount, $installment->currency) }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">{{ $installment->admin?->name ?: 'Unknown' }}</td>
                            <td class="border border-gray-200 !px-3 !py-2.5">{{ $installment->sourceLabel() }}</td>
                            <td class="border border-gray-200 !px-3 !py-2.5">{{ $installment->note ?: '—' }}</td>
                            <td class="border border-gray-200 !px-3 !py-2.5">{{ $installment->allocationSummary() ?: '—' }}</td>
                            <td class="border border-gray-200 !px-3 !py-2.5 whitespace-nowrap">
                                @include('admin.local-clients.partials.installment-revert-form')
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="border border-gray-200 !px-3 !py-8 text-center text-gray-500">No installments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lc-mobile-cards !mt-2">
            @forelse ($installments as $index => $installment)
                <article class="lc-mobile-card">
                    <div class="text-xs text-gray-500">S.No {{ $installments->firstItem() + $index }}</div>
                    <div class="font-semibold text-gray-900 tabular-nums">
                        {{ \App\Support\CurrencyFormatter::format($installment->amount, $installment->currency) }}
                    </div>
                    <div class="text-sm text-gray-700 !mt-1">
                        @if ($installment->localClient)
                            <a href="{{ route('admin.local-clients.show', $installment->localClient) }}" class="font-semibold hover:text-[var(--primary-color)]">{{ $installment->localClient->name }}</a>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 !mt-1">
                        {{ $installment->created_at?->format('M d, Y g:i A') }}
                        · {{ $installment->admin?->name ?: 'Unknown' }}
                        · {{ $installment->sourceLabel() }}
                    </div>
                    @if ($installment->note)
                        <div class="text-sm text-gray-700 !mt-1">{{ $installment->note }}</div>
                    @endif
                    @if ($installment->allocationSummary() !== '')
                        <div class="text-xs text-gray-600 !mt-1">{{ $installment->allocationSummary() }}</div>
                    @endif
                    <div class="!mt-2">
                        @include('admin.local-clients.partials.installment-revert-form')
                    </div>
                </article>
            @empty
                <div class="lc-empty-state">
                    <div class="material-symbols-outlined">savings</div>
                    <p class="font-medium text-gray-700">No installments found</p>
                </div>
            @endforelse
        </div>

        @if ($installments->total() > 0)
            <div class="!mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pagination">
                <p class="text-sm text-gray-500">
                    Showing {{ $installments->firstItem() }}–{{ $installments->lastItem() }} of {{ $installments->total() }}
                </p>
                {{ $installments->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('form[data-lc-once]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.submitted === '1') {
                    event.preventDefault();
                    return;
                }

                const message = form.getAttribute('data-lc-confirm');
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                    return;
                }

                form.dataset.submitted = '1';
                const button = form.querySelector('[type="submit"]');
                if (!button) {
                    return;
                }

                window.setTimeout(function () {
                    button.disabled = true;
                }, 0);
            });
        });
    </script>
@endpush
