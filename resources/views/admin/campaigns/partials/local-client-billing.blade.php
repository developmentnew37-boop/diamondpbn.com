@if ($campaign->local_client_id && $campaign->billing_snapshot)
    @php
        $billableType = \App\Support\BillableCampaignRegistry::typeForModel($campaign);
        $snapshot = is_array($campaign->billing_snapshot) ? $campaign->billing_snapshot : json_decode($campaign->billing_snapshot, true);
        $currency = $campaign->billing_currency ?? 'USD';
        $client = $campaign->localClient ?? \App\Models\Admin\LocalClient::find($campaign->local_client_id);
        $isPaid = ($campaign->billing_payment_status ?? 'unpaid') === 'paid';
        $lineCount = count($snapshot['lines'] ?? []);
        $lcbStorageKey = $billableType.'-'.$campaign->id;
        $hasBalanceCredit = \App\Services\LocalClientBillingService::hasBalanceCredit(
            $campaign->billing_amount_paid ?? null,
            $campaign->billing_payment_status ?? null,
        );
        $balanceDue = \App\Services\LocalClientBillingService::balanceDue(
            $campaign->billing_total,
            $campaign->billing_amount_paid ?? null,
            $campaign->billing_payment_status ?? null,
        );
    @endphp

    @once
        @push('style')
            @include('admin.campaigns.partials.local-client-billing-styles')
        @endpush
        @push('scripts')
            <script src="{{ asset('js/local-client-billing-toggle.js') }}?v={{ filemtime(public_path('js/local-client-billing-toggle.js')) }}"></script>
        @endpush
    @endonce

    <div class="lcb-view-wrapper" data-lcb-view data-lcb-key="{{ $lcbStorageKey }}">
        <div class="lcb-view-bar">
            <div class="lcb-view-bar-left">
                <div class="lcb-view-bar-icon" aria-hidden="true">
                    <span class="material-symbols-outlined">receipt_long</span>
                </div>
                <span class="lcb-view-bar-title">Client Billing</span>
                <span class="lcb-payment-badge {{ $isPaid ? 'paid' : 'unpaid' }}">
                    {{ $isPaid ? 'Paid' : 'Unpaid' }}
                </span>
                <span class="lcb-view-bar-meta">
                    {{ $client?->name ?? '—' }}
                    ·
                    <strong>{{ \App\Support\CurrencyFormatter::format($campaign->billing_total, $currency) }}</strong>
                </span>
            </div>
            <div class="lcb-view-bar-actions">
                <button type="button"
                        class="lcb-toggle-btn"
                        data-lcb-toggle-btn
                        aria-expanded="false"
                        aria-controls="lcb-view-panel-{{ $lcbStorageKey }}">
                    <span data-lcb-toggle-label>Show billing</span>
                    <span class="material-symbols-outlined" data-lcb-toggle-icon>expand_more</span>
                </button>
            </div>
        </div>

        <div class="lcb-view-panel is-collapsed"
             id="lcb-view-panel-{{ $lcbStorageKey }}"
             data-lcb-panel>
            <div class="lcb-view-top">
                <div class="lcb-view-heading">
                    <div class="lcb-panel-icon" aria-hidden="true">
                        <span class="material-symbols-outlined">receipt_long</span>
                    </div>
                    <div class="min-w-0">
                        <div class="lcb-panel-title-row">
                            <h3 class="lcb-panel-title">Client Billing</h3>
                            <span class="lcb-payment-badge {{ $isPaid ? 'paid' : 'unpaid' }}">
                                {{ $isPaid ? 'Paid' : 'Unpaid' }}
                            </span>
                        </div>
                        <p class="lcb-panel-desc">
                            Current billed amount for this campaign’s domains.
                            Unpaid totals update automatically when a domain is replaced; use Sync billing to rebuild from current domains (and to reopen a paid invoice).
                            @if ($lineCount > 0)
                                {{ $lineCount }} {{ $lineCount === 1 ? 'domain' : 'domains' }} priced.
                            @endif
                        </p>
                    </div>
                </div>
                <div class="lcb-view-actions">
                    @if (Auth::guard('admin')->user()?->isSuperAdmin() || (int) $campaign->admin_id === (int) Auth::guard('admin')->id())
                        <form method="POST"
                              action="{{ route('admin.local-clients.billing.sync', ['billableType' => $billableType, 'id' => $campaign->id]) }}"
                              class="inline"
                              @if ($isPaid)
                              onsubmit="return confirm('This recalculates billing from current domains and marks the campaign unpaid. Continue?');"
                              @endif>
                            @csrf
                            <button type="submit" class="lcb-btn-invoice">
                                <span class="material-symbols-outlined">sync</span>
                                Sync billing
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('admin.local-clients.campaign-invoice', ['billableType' => $billableType, 'id' => $campaign->id]) }}"
                       class="lcb-btn-invoice">
                        <span class="material-symbols-outlined">download</span>
                        Download Invoice
                    </a>
                </div>
            </div>

            <div class="lcb-view-stats">
                <div class="lcb-stat-card">
                    <span class="lcb-stat-label">Client</span>
                    <span class="lcb-stat-value">
                        @if ($client && Auth::guard('admin')->user()?->canManageLocalClients())
                            <a href="{{ route('admin.local-clients.show', $client) }}">{{ $client->name }}</a>
                        @else
                            {{ $client?->name ?? '—' }}
                        @endif
                    </span>
                </div>
                <div class="lcb-stat-card">
                    <span class="lcb-stat-label">Billing total</span>
                    <span class="lcb-stat-value total">{{ \App\Support\CurrencyFormatter::format($campaign->billing_total, $currency) }}</span>
                </div>
                @if ($hasBalanceCredit)
                    <div class="lcb-stat-card">
                        <span class="lcb-stat-label">Already paid</span>
                        <span class="lcb-stat-value">{{ \App\Support\CurrencyFormatter::format($campaign->billing_amount_paid, $currency) }}</span>
                    </div>
                    <div class="lcb-stat-card">
                        <span class="lcb-stat-label">Due now</span>
                        <span class="lcb-stat-value total">{{ \App\Support\CurrencyFormatter::format($balanceDue, $currency) }}</span>
                    </div>
                @endif
                <div class="lcb-stat-card">
                    <span class="lcb-stat-label">Currency</span>
                    <span class="lcb-stat-value">{{ $currency }} · {{ \App\Support\CurrencyFormatter::name($currency) }}</span>
                </div>
            </div>

            @if (!empty($snapshot['lines']))
                <details class="lcb-breakdown">
                    <summary>
                        <span class="material-symbols-outlined">chevron_right</span>
                        View line breakdown ({{ $lineCount }})
                    </summary>
                    <div class="lcb-breakdown-table-wrap">
                        <table class="lcb-breakdown-table">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Category</th>
                                    <th>Unit price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($snapshot['lines'] as $line)
                                    <tr>
                                        <td>{{ $line['domain_name'] ?? '—' }}</td>
                                        <td>{{ $line['category_name'] ?? '—' }}</td>
                                        <td class="price-col">{{ \App\Support\CurrencyFormatter::format($line['unit_price'] ?? 0, $currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif

            @if (Auth::guard('admin')->user()?->isSuperAdmin() || (int) $campaign->admin_id === (int) Auth::guard('admin')->id())
                <form method="POST"
                      action="{{ route('admin.local-clients.payment.update', ['billableType' => $billableType, 'id' => $campaign->id]) }}"
                      class="lcb-payment-form">
                    @csrf
                    @method('PATCH')
                    <div class="lcb-payment-form-title">
                        <span class="material-symbols-outlined">edit_note</span>
                        Update payment
                    </div>
                    <div class="lcb-payment-form-grid">
                        <div class="lcb-field">
                            <label for="billing_payment_status_{{ $campaign->id }}" class="lcb-label">Payment status</label>
                            <select name="billing_payment_status" id="billing_payment_status_{{ $campaign->id }}" class="lcb-select">
                                <option value="unpaid" @selected(!$isPaid)>Unpaid</option>
                                <option value="paid" @selected($isPaid)>Paid</option>
                            </select>
                        </div>
                        <div class="lcb-field">
                            <label for="billing_payment_note_{{ $campaign->id }}" class="lcb-label">Note</label>
                            <input type="text"
                                   name="billing_payment_note"
                                   id="billing_payment_note_{{ $campaign->id }}"
                                   value="{{ $campaign->billing_payment_note }}"
                                   placeholder="Optional payment note"
                                   class="lcb-input">
                        </div>
                        <button type="submit" class="lcb-btn-update">
                            <span class="material-symbols-outlined !text-base">save</span>
                            Update
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endif
