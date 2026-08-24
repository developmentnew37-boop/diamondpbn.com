@extends('admin.layout.layout')

@section('title', $localClient->name)

@push('style')
    @include('admin.local-clients.partials.styles')
@endpush

@section('main-content')

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
            <div class="w-full sm:w-auto flex flex-col gap-2 min-w-0">
                <h2 class="page-title">{{ $localClient->name }}</h2>
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
                        <span class="breadcrumb-link">{{ $localClient->name }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 !mt-1">
                    @if ($localClient->company_name)
                        <span class="text-sm text-gray-600">{{ $localClient->company_name }}</span>
                        <span class="text-gray-300">·</span>
                    @endif
                    <span class="lc-currency-badge">{{ $localClient->default_currency }}</span>
                    <span class="lc-status-badge {{ $localClient->is_active ? 'active' : 'inactive' }}">
                        {{ $localClient->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span class="text-sm text-gray-600">
                        Rates:
                        @if ($localClient->rateList)
                            {{ $localClient->rateList->name }}
                        @else
                            Customize
                        @endif
                    </span>
                </div>
            </div>
            <div class="w-full sm:w-auto flex flex-wrap gap-2 shrink-0">
                <a href="{{ route('admin.local-clients.edit', $localClient) }}" class="lc-theme-btn !min-h-[42px]">
                    <span class="material-symbols-outlined !text-base">edit_square</span>
                    Edit
                </a>
                <form method="POST" action="{{ route('admin.local-clients.toggle-active', $localClient) }}">
                    @csrf
                    <button type="submit" class="lc-btn-secondary !min-h-[42px]">
                        <span class="material-symbols-outlined !text-base">{{ $localClient->is_active ? 'block' : 'check_circle' }}</span>
                        {{ $localClient->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.local-clients.destroy', $localClient) }}"
                    onsubmit="return confirm({{ json_encode('Delete client ' . $localClient->name . '? This removes pricing, billing periods, payment history, and unlinks all campaigns. This cannot be undone.') }});">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="lc-btn-danger !min-h-[42px]">
                        <span class="material-symbols-outlined !text-base">delete</span>
                        Delete client
                    </button>
                </form>
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

    {{-- Summary cards --}}
    <div class="w-full grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 !mt-4">
        <div class="lc-stat-card">
            <div class="lc-stat-label">Total billed</div>
            <div class="lc-stat-value">{{ $summary['total_billed'] }}</div>
        </div>
        <div class="lc-stat-card">
            <div class="lc-stat-label">Paid ({{ $summary['paid_count'] }})</div>
            <div class="lc-stat-value paid">{{ $summary['total_paid'] }}</div>
        </div>
        <div class="lc-stat-card">
            <div class="lc-stat-label">Unpaid ({{ $summary['unpaid_count'] }})</div>
            <div class="lc-stat-value unpaid">{{ $summary['total_unpaid'] }}</div>
        </div>
        <div class="lc-stat-card">
            <div class="lc-stat-label">Shareable report</div>
            <div class="lc-report-wrap">
                <input type="text" readonly value="{{ $reportUrl }}" class="lc-report-input" id="report-url">
                <button type="button" class="lc-report-copy-icon" id="report-url-copy-icon" title="Copy link"
                    aria-label="Copy report link">
                    <span class="material-symbols-outlined !text-base">content_copy</span>
                </button>
            </div>
            <div class="flex flex-wrap gap-2 !mt-3">
                <button type="button" class="lc-chip-btn primary" id="report-copy-btn">
                    <span class="material-symbols-outlined">content_copy</span>
                    <span class="lc-chip-label">Copy link</span>
                </button>
                <form method="POST" action="{{ route('admin.local-clients.regenerate-token', $localClient) }}" class="inline">
                    @csrf
                    <button type="submit" class="lc-chip-btn danger"
                        onclick="return confirm('Regenerate link? Previous shared links will stop working.')">
                        <span class="material-symbols-outlined">refresh</span>
                        Regenerate
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Billing periods --}}
    <div class="w-full content-card min-w-0 !mt-4">
        <div class="lc-section-heading !mb-3">
            <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">date_range</span>
            <h3>Billing Periods</h3>
        </div>
        <p class="lc-help-text !mb-5">
            Settle orders in bulk by billing period. Campaign-level breakdown is available via the shareable report link above.
        </p>

        <div class="lc-desktop-table overflow-x-auto w-full max-w-full min-w-0">
            <table class="w-full min-w-[760px] border border-gray-200 border-collapse text-sm">
                <thead>
                    <tr class="bg-[var(--sidebar-bg)] text-white">
                        @foreach (['Period', 'Campaigns', 'Total', 'Status', 'Actions'] as $heading)
                            <th class="border border-gray-200 font-sans !font-normal !px-3 !py-3 text-left">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($periods as $period)
                        <tr class="hover:bg-gray-50 border-b border-gray-100">
                            <td class="border border-gray-200 !px-3 !py-2.5 text-gray-900 whitespace-nowrap">
                                <span class="font-medium">{{ $period['period_start']->format('M d, Y') }}</span>
                                <span class="text-gray-400 mx-1">→</span>
                                @if ($period['is_open'])
                                    <span class="font-medium text-[var(--primary-color)]">Present</span>
                                @else
                                    <span class="font-medium">{{ $period['period_end']->format('M d, Y') }}</span>
                                @endif
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5 text-gray-700 tabular-nums">
                                {{ $period['campaign_count'] }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5 font-semibold text-gray-900 tabular-nums">
                                {{ $period['formatted_total'] }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <span class="lc-status-badge {{ $period['status'] === 'paid' ? 'paid' : 'unpaid' }}">
                                    {{ ucfirst($period['status']) }}
                                </span>
                                @if ($period['paid_at'])
                                    <div class="text-xs text-gray-500 !mt-1">
                                        Paid {{ $period['paid_at']->format('M d, Y g:i A') }}
                                    </div>
                                @endif
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <div class="flex flex-wrap gap-3 items-center">
                                    @if ($period['is_open'])
                                        <form method="POST"
                                            action="{{ route('admin.local-clients.mark-period-paid', $localClient) }}"
                                            class="inline"
                                            onsubmit="return confirm('Mark all {{ $period['campaign_count'] }} unpaid campaign(s) in this period as paid?');">
                                            @csrf
                                            <button type="submit" class="lc-theme-btn !min-h-[36px] !py-2 !px-3 !text-xs">
                                                <span class="material-symbols-outlined !text-sm">payments</span>
                                                Mark paid
                                            </button>
                                        </form>
                                    @endif
                                    @include('admin.local-clients.partials.billing-period-delete-form', ['period' => $period, 'localClient' => $localClient])
                                    <a href="{{ $reportUrl }}" target="_blank" rel="noopener noreferrer" class="lc-table-btn lc-table-btn-view">
                                        <span class="material-symbols-outlined">open_in_new</span>
                                        View campaigns
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="border border-gray-200">
                                <div class="lc-empty-state">
                                    <div class="material-symbols-outlined">receipt</div>
                                    <p class="font-medium text-gray-700">No billing periods yet</p>
                                    <p class="text-sm !mt-1">Assign this client when creating a campaign to start billing.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lc-mobile-cards">
            @forelse ($periods as $period)
                <article class="lc-mobile-card">
                    <div class="lc-mobile-card-head">
                        <div class="lc-mobile-card-title">
                            {{ $period['period_start']->format('M d, Y') }}
                            →
                            @if ($period['is_open'])
                                Present
                            @else
                                {{ $period['period_end']->format('M d, Y') }}
                            @endif
                        </div>
                        <span class="lc-status-badge {{ $period['status'] === 'paid' ? 'paid' : 'unpaid' }}">
                            {{ ucfirst($period['status']) }}
                        </span>
                    </div>
                    <div class="lc-mobile-card-meta">
                        <div>
                            <label>Campaigns</label>
                            <span>{{ $period['campaign_count'] }}</span>
                        </div>
                        <div>
                            <label>Total</label>
                            <span>{{ $period['formatted_total'] }}</span>
                        </div>
                    </div>
                    @if ($period['paid_at'])
                        <div class="text-xs text-gray-500 !mt-2">
                            Paid {{ $period['paid_at']->format('M d, Y g:i A') }}
                        </div>
                    @endif
                    <div class="lc-mobile-card-actions">
                        @if ($period['is_open'])
                            <form method="POST"
                                action="{{ route('admin.local-clients.mark-period-paid', $localClient) }}"
                                class="inline"
                                onsubmit="return confirm('Mark all {{ $period['campaign_count'] }} unpaid campaign(s) in this period as paid?');">
                                @csrf
                                <button type="submit" class="lc-theme-btn !min-h-[36px] !py-2 !px-3 !text-xs">
                                    <span class="material-symbols-outlined !text-sm">payments</span>
                                    Mark paid
                                </button>
                            </form>
                        @endif
                        @include('admin.local-clients.partials.billing-period-delete-form', ['period' => $period, 'localClient' => $localClient])
                        <a href="{{ $reportUrl }}" target="_blank" rel="noopener noreferrer" class="lc-table-btn lc-table-btn-view">
                            <span class="material-symbols-outlined">open_in_new</span>
                            View campaigns
                        </a>
                    </div>
                </article>
            @empty
                <div class="lc-empty-state">
                    <div class="material-symbols-outlined">receipt</div>
                    <p class="font-medium text-gray-700">No billing periods yet</p>
                </div>
            @endforelse
        </div>

        @if ($periods->hasPages())
            <div class="!mt-4">
                {{ $periods->links() }}
            </div>
        @endif
    </div>

    <div class="lc-toast-stack" id="lc-toast-stack" aria-live="polite" aria-atomic="true"></div>

@endsection

@push('scripts')
    <script>
        (function () {
            const reportInput = document.getElementById('report-url');
            const copyBtn = document.getElementById('report-copy-btn');
            const copyIconBtn = document.getElementById('report-url-copy-icon');
            const toastStack = document.getElementById('lc-toast-stack');

            if (!reportInput || !toastStack) {
                return;
            }

            function showToast(message, type) {
                const toast = document.createElement('div');
                toast.className = 'lc-toast is-' + (type || 'success');
                toast.innerHTML =
                    '<span class="material-symbols-outlined">' +
                    (type === 'error' ? 'error' : 'check_circle') +
                    '</span><span>' + message + '</span>';
                toastStack.appendChild(toast);

                requestAnimationFrame(function () {
                    toast.classList.add('is-visible');
                });

                setTimeout(function () {
                    toast.classList.remove('is-visible');
                    setTimeout(function () {
                        toast.remove();
                    }, 250);
                }, 3200);
            }

            function fallbackCopy(text) {
                reportInput.focus();
                reportInput.select();
                reportInput.setSelectionRange(0, text.length);

                try {
                    return document.execCommand('copy');
                } catch (err) {
                    return false;
                }
            }

            function setCopiedState(active) {
                if (copyBtn) {
                    const label = copyBtn.querySelector('.lc-chip-label');
                    const icon = copyBtn.querySelector('.material-symbols-outlined');

                    if (active) {
                        copyBtn.classList.add('is-copied');
                        if (label) {
                            label.textContent = 'Copied!';
                        }
                        if (icon) {
                            icon.textContent = 'check';
                        }
                    } else {
                        copyBtn.classList.remove('is-copied');
                        if (label) {
                            label.textContent = 'Copy link';
                        }
                        if (icon) {
                            icon.textContent = 'content_copy';
                        }
                    }
                }

                if (copyIconBtn) {
                    const icon = copyIconBtn.querySelector('.material-symbols-outlined');
                    copyIconBtn.classList.toggle('is-copied', active);
                    if (icon) {
                        icon.textContent = active ? 'check' : 'content_copy';
                    }
                }
            }

            function copyReportLink() {
                const text = reportInput.value;
                if (!text) {
                    return;
                }

                const onSuccess = function () {
                    setCopiedState(true);
                    showToast('Link copied to clipboard');
                    setTimeout(function () {
                        setCopiedState(false);
                    }, 2500);
                };

                const onFailure = function () {
                    if (fallbackCopy(text)) {
                        onSuccess();
                        return;
                    }

                    showToast('Could not copy — please select the link manually', 'error');
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(onSuccess).catch(onFailure);
                    return;
                }

                onFailure();
            }

            if (copyBtn) {
                copyBtn.addEventListener('click', copyReportLink);
            }

            if (copyIconBtn) {
                copyIconBtn.addEventListener('click', copyReportLink);
            }
        })();
    </script>
@endpush
