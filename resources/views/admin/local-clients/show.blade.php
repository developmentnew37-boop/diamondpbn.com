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
                <a href="{{ route('admin.local-clients.installments.index', ['client_id' => $localClient->id]) }}" class="lc-theme-btn !min-h-[42px]">
                    <span class="material-symbols-outlined !text-base">account_balance_wallet</span>
                    Installments
                </a>
                @include('admin.local-clients.partials.manual-billing-button')
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
        @if ($errors->any())
            <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                @foreach ($errors->all() as $error)
                    <span class="font-medium">{{ $error }}</span>
                @endforeach
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
            Record an installment against the open balance, or mark the whole period paid. An installment pays the oldest campaigns first. A campaign that is only partly covered becomes semi paid.
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
                                        @include('admin.local-clients.partials.installment-form')
                                        <form method="POST"
                                            action="{{ route('admin.local-clients.mark-period-paid', $localClient) }}"
                                            class="inline"
                                            data-lc-once
                                            data-lc-confirm="Mark all {{ $period['campaign_count'] }} unpaid campaign(s) in this period as paid?">
                                            @csrf
                                            <button type="submit" class="lc-theme-btn !min-h-[36px] !py-2 !px-3 !text-xs">
                                                <span class="material-symbols-outlined !text-sm">payments</span>
                                                Mark paid
                                            </button>
                                        </form>
                                    @endif
                                    @include('admin.local-clients.partials.mark-period-unpaid-form', ['period' => $period, 'localClient' => $localClient])
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
                            @include('admin.local-clients.partials.installment-form')
                            <form method="POST"
                                action="{{ route('admin.local-clients.mark-period-paid', $localClient) }}"
                                class="inline"
                                data-lc-once
                                data-lc-confirm="Mark all {{ $period['campaign_count'] }} unpaid campaign(s) in this period as paid?">
                                @csrf
                                <button type="submit" class="lc-theme-btn !min-h-[36px] !py-2 !px-3 !text-xs">
                                    <span class="material-symbols-outlined !text-sm">payments</span>
                                    Mark paid
                                </button>
                            </form>
                        @endif
                        @include('admin.local-clients.partials.mark-period-unpaid-form', ['period' => $period, 'localClient' => $localClient])
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

    @include('admin.local-clients.partials.installment-modal')
    @include('admin.local-clients.partials.manual-billing-modal')
    @foreach ($periods as $period)
        @include('admin.local-clients.partials.mark-period-unpaid-modal', ['period' => $period, 'localClient' => $localClient])
    @endforeach

    <div class="lc-toast-stack" id="lc-toast-stack" aria-live="polite" aria-atomic="true"></div>

@endsection

@push('scripts')
    <script>
        function unpaidRows(form) {
            return Array.prototype.slice.call(form.querySelectorAll('[data-lc-unpaid-row]'));
        }

        function unpaidBox(row) {
            return row.querySelector('input[name="campaigns[]"]');
        }

        function refreshUnpaidForm(form) {
            const rows = unpaidRows(form);
            const selected = rows.filter(function (row) {
                const box = unpaidBox(row);
                return box && box.checked;
            }).length;
            const count = form.querySelector('[data-lc-unpaid-count]');
            if (count) {
                count.textContent = 'Selected ' + selected + ' of ' + rows.length;
            }
            form.setAttribute('data-lc-confirm', 'Mark ' + selected + ' of ' + rows.length + ' campaigns as unpaid?');

            const visible = rows.filter(function (row) {
                return !row.hidden;
            });
            const master = form.querySelector('[data-lc-unpaid-all]');
            if (master) {
                master.checked = visible.length > 0 && visible.every(function (row) {
                    const box = unpaidBox(row);
                    return box && box.checked;
                });
            }
        }

        function applyUnpaidSearch(form) {
            const input = form.querySelector('[data-lc-unpaid-search]');
            const needle = input ? input.value.trim().toLowerCase() : '';
            unpaidRows(form).forEach(function (row) {
                const haystack = (row.getAttribute('data-lc-unpaid-match') || '').toLowerCase();
                row.hidden = needle !== '' && haystack.indexOf(needle) === -1;
            });
            refreshUnpaidForm(form);
        }

        function resetUnpaidSearch(form) {
            const input = form.querySelector('[data-lc-unpaid-search]');
            if (input) {
                input.value = '';
            }
            applyUnpaidSearch(form);
        }

        document.querySelectorAll('form [data-lc-unpaid-search]').forEach(function (input) {
            const form = input.closest('form');
            if (!form) {
                return;
            }
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });
            input.addEventListener('input', function () {
                applyUnpaidSearch(form);
            });
            form.querySelectorAll('input[name="campaigns[]"]').forEach(function (box) {
                box.addEventListener('change', function () {
                    refreshUnpaidForm(form);
                });
            });
            const master = form.querySelector('[data-lc-unpaid-all]');
            if (master) {
                master.addEventListener('change', function () {
                    unpaidRows(form).forEach(function (row) {
                        if (row.hidden) {
                            return;
                        }
                        const box = unpaidBox(row);
                        if (box) {
                            box.checked = master.checked;
                        }
                    });
                    refreshUnpaidForm(form);
                });
            }
            refreshUnpaidForm(form);
        });

        document.querySelectorAll('[data-lc-unpaid-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                const modal = document.getElementById(button.getAttribute('data-lc-unpaid-open'));
                if (!modal) {
                    return;
                }
                const form = modal.querySelector('form');
                if (form) {
                    resetUnpaidSearch(form);
                }
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
            });
        });
        document.querySelectorAll('[data-lc-unpaid-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                const modal = button.closest('.lc-modal');
                if (!modal) {
                    return;
                }
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
            });
        });
    </script>
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
                    const label = button.querySelector('[data-lc-once-label]');
                    const recording = form.getAttribute('data-lc-recording');
                    if (label && recording) {
                        label.textContent = recording;
                    }
                }, 0);
            });
        });
    </script>
    <script>
        (function () {
            const modal = document.getElementById('lc-installment-modal');
            if (!modal) {
                return;
            }

            const amountInput = document.getElementById('installment-amount');

            function openInstallmentModal() {
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                if (amountInput) {
                    amountInput.focus();
                }
            }

            function closeInstallmentModal() {
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            document.querySelectorAll('[data-lc-installment-open]').forEach(function (button) {
                button.addEventListener('click', openInstallmentModal);
            });

            modal.querySelectorAll('[data-lc-installment-close]').forEach(function (button) {
                button.addEventListener('click', closeInstallmentModal);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeInstallmentModal();
                }
            });

            @if ($errors->has('amount') || $errors->has('note'))
                openInstallmentModal();
            @endif
        })();
    </script>
    <script>
        (function () {
            const modal = document.getElementById('lc-manual-billing-modal');
            const configNode = document.getElementById('lc-manual-billing-config');
            if (!modal || !configNode) {
                return;
            }

            const config = JSON.parse(configNode.textContent || '{}');
            const typeSelect = document.getElementById('lc-manual-campaign-type');
            const liveLabel = document.getElementById('lc-manual-live-label');
            const totalNode = document.getElementById('lc-manual-total');
            const totalError = document.getElementById('lc-manual-total-error');
            const linesNode = document.getElementById('lc-manual-lines');
            const quantityInput = document.getElementById('lc-manual-post-quantity');
            const keywordInput = document.getElementById('lc-manual-keyword');
            const urlInput = document.getElementById('lc-manual-url');
            const linkCountNode = document.getElementById('lc-manual-link-count');
            const keywordCountNode = document.getElementById('lc-manual-keyword-count');
            const urlCountNode = document.getElementById('lc-manual-url-count');
            const postLinksInput = document.getElementById('lc-manual-post-links');
            const matchNode = document.getElementById('lc-manual-match');
            const saveButton = document.getElementById('lc-manual-save');
            const form = document.getElementById('lc-manual-billing-form');
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const domainTypes = { sidebar: true, hidden_links: true, schedule_sidebar: true };
            let previewTimer = null;
            let previewToken = 0;
            let pricedSignature = '';

            function openModal() {
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                document.getElementById('lc-manual-campaign-no')?.focus();
            }

            function closeModal() {
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            function usesDomains() {
                return !!domainTypes[typeSelect?.value];
            }

            function liveSignature() {
                return (typeSelect?.value || '') + '\n' + (postLinksInput?.value || '');
            }

            function entryLines(value) {
                return String(value || '').split(/\r\n|\n|\r/).map(function (line) {
                    return line.trim();
                }).filter(Boolean);
            }

            function syncLiveLabel() {
                const unit = usesDomains() ? 'Domains' : 'Live post links';
                if (liveLabel) {
                    liveLabel.textContent = unit;
                }
            }

            function canSave() {
                const quantity = entryLines(postLinksInput?.value).length;
                const keywordCount = entryLines(keywordInput?.value).length;
                const urlCount = entryLines(urlInput?.value).length;
                return quantity > 0
                    && quantity <= 500
                    && keywordCount === quantity
                    && urlCount === quantity
                    && pricedSignature === liveSignature();
            }

            function syncMatch() {
                const quantity = entryLines(postLinksInput?.value).length;
                const keywordCount = entryLines(keywordInput?.value).length;
                const urlCount = entryLines(urlInput?.value).length;
                const unit = usesDomains() ? 'domains' : 'links';
                if (quantityInput) {
                    quantityInput.value = String(quantity);
                }
                if (linkCountNode) {
                    linkCountNode.textContent = quantity + ' ' + unit;
                }
                if (keywordCountNode) {
                    keywordCountNode.textContent = keywordCount + ' of ' + quantity + ' keywords';
                }
                if (urlCountNode) {
                    urlCountNode.textContent = urlCount + ' of ' + quantity + ' URLs';
                }
                const matched = quantity > 0 && quantity <= 500 && keywordCount === quantity && urlCount === quantity;
                if (saveButton) {
                    saveButton.disabled = !canSave();
                }
                if (!matchNode) {
                    return matched;
                }
                if (quantity > 500) {
                    matchNode.hidden = false;
                    matchNode.textContent = 'A manual campaign can include at most 500 lines.';
                    return false;
                }
                if (matched || quantity === 0) {
                    matchNode.hidden = true;
                    matchNode.textContent = '';
                    return matched;
                }
                matchNode.hidden = false;
                matchNode.textContent = 'Live lines are ' + quantity
                    + ', keywords are ' + keywordCount
                    + ', and URLs are ' + urlCount + '.';
                return matched;
            }

            function schedulePreview() {
                pricedSignature = '';
                syncMatch();
                window.clearTimeout(previewTimer);
                previewTimer = window.setTimeout(previewTotal, 250);
            }

            function previewTotal() {
                const signature = liveSignature();
                const requestId = ++previewToken;
                linesNode.innerHTML = '';
                totalError.hidden = true;
                totalError.textContent = '';
                if (entryLines(postLinksInput?.value).length === 0) {
                    totalNode.textContent = '—';
                    pricedSignature = '';
                    syncMatch();
                    return;
                }

                totalNode.textContent = 'Calculating…';
                const body = new FormData();
                body.append('campaign_type', typeSelect.value);
                body.append('post_links', postLinksInput?.value || '');

                fetch(config.previewUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: body,
                }).then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                }).then(function (result) {
                    if (requestId !== previewToken || signature !== liveSignature()) {
                        return;
                    }
                    if (!result.ok) {
                        const message = result.data?.errors?.post_links?.[0]
                            || result.data?.message
                            || 'Could not calculate the price.';
                        totalNode.textContent = '—';
                        totalError.textContent = message;
                        totalError.hidden = false;
                        pricedSignature = '';
                        syncMatch();
                        return;
                    }
                    if ((result.data.missing || []).length > 0) {
                        totalNode.textContent = '—';
                        totalError.textContent = 'These domains are not in the inventory: ' + result.data.missing.join(', ') + '.';
                        totalError.hidden = false;
                        pricedSignature = '';
                        syncMatch();
                        return;
                    }

                    totalNode.textContent = result.data.formatted_total || result.data.total || '—';
                    (result.data.lines || []).forEach(function (line) {
                        const item = document.createElement('li');
                        item.textContent = line.domain_name + ' · ' + line.formatted_line_total;
                        linesNode.appendChild(item);
                    });
                    pricedSignature = signature;
                    syncMatch();
                }).catch(function () {
                    if (requestId !== previewToken) {
                        return;
                    }
                    totalNode.textContent = '—';
                    totalError.textContent = 'Could not calculate the price.';
                    totalError.hidden = false;
                    pricedSignature = '';
                    syncMatch();
                });
            }

            document.querySelectorAll('[data-lc-manual-billing-open]').forEach(function (button) {
                button.addEventListener('click', openModal);
            });
            modal.querySelectorAll('[data-lc-manual-billing-close]').forEach(function (button) {
                button.addEventListener('click', closeModal);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            });

            typeSelect?.addEventListener('change', function () {
                syncLiveLabel();
                schedulePreview();
            });

            postLinksInput?.addEventListener('input', schedulePreview);
            keywordInput?.addEventListener('input', syncMatch);
            urlInput?.addEventListener('input', syncMatch);
            form?.addEventListener('submit', function (event) {
                if (!canSave()) {
                    event.preventDefault();
                }
            });

            syncLiveLabel();
            syncMatch();
            if (entryLines(postLinksInput?.value).length > 0) {
                schedulePreview();
            }
            if (config.openOnError) {
                openModal();
            }
        })();
    </script>
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
