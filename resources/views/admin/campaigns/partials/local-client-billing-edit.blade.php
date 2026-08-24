@php
    $canEditClient = Auth::guard('admin')->user()?->isSuperAdmin()
        || (int) ($campaign->admin_id ?? 0) === (int) Auth::guard('admin')->id();
    $localClients = $localClients ?? collect();
@endphp

@if ($canEditClient && $localClients->isNotEmpty())
    @php
        $billableType = \App\Support\BillableCampaignRegistry::typeForModel($campaign);
        $currencies = \App\Support\CurrencyFormatter::supportedCodes();
        $selectedClientId = old('local_client_id', $campaign->local_client_id);
        $selectedCurrency = old(
            'billing_currency',
            $campaign->billing_currency
                ?? $localClients->firstWhere('id', (int) $selectedClientId)?->default_currency
                ?? 'USD'
        );
        $isPaid = ($campaign->billing_payment_status ?? 'unpaid') === 'paid';
        $hasBilling = (bool) $campaign->local_client_id && ! empty($campaign->billing_snapshot);
    @endphp

    @once
        @push('style')
            @include('admin.campaigns.partials.local-client-billing-styles')
        @endpush
    @endonce

    <div class="lcb-view-wrapper !mt-4" data-lcb-edit>
        <div class="lcb-view-bar" style="flex-direction: column; align-items: stretch; gap: 0.875rem;">
            <div class="lcb-view-bar-left">
                <div class="lcb-view-bar-icon" aria-hidden="true">
                    <span class="material-symbols-outlined">payments</span>
                </div>
                <span class="lcb-view-bar-title">Local Client Billing</span>
                <span class="lcb-optional-badge">Optional</span>
                @if ($hasBilling)
                    <span class="lcb-view-bar-meta">
                        {{ $campaign->localClient?->name ?? '—' }}
                        ·
                        <strong>{{ \App\Support\CurrencyFormatter::format($campaign->billing_total, $campaign->billing_currency ?? 'USD') }}</strong>
                    </span>
                    <span class="lcb-payment-badge {{ $isPaid ? 'paid' : 'unpaid' }}">
                        {{ $isPaid ? 'Paid' : 'Unpaid' }}
                    </span>
                @endif
            </div>

            <p class="text-sm text-gray-600 !m-0">
                Attach, change, or remove the billing client for this campaign.
                @if ($isPaid)
                    Saving will reset payment status to unpaid and recalculate the billing total.
                @else
                    Saving recalculates the billing total from this campaign’s domains.
                @endif
            </p>

            <form method="POST"
                  action="{{ route('admin.local-clients.client.update', ['billableType' => $billableType, 'id' => $campaign->id]) }}"
                  class="w-full flex flex-wrap items-end gap-3">
                @csrf
                @method('PATCH')

                <div class="lcb-field" style="min-width: 12rem; flex: 1 1 14rem;">
                    <label for="lcb-edit-client-{{ $campaign->id }}" class="lcb-label">Client</label>
                    <select name="local_client_id"
                            id="lcb-edit-client-{{ $campaign->id }}"
                            class="lcb-select"
                            data-lcb-edit-client>
                        <option value="">— No client —</option>
                        @foreach ($localClients as $client)
                            <option value="{{ $client->id }}"
                                    data-currency="{{ $client->default_currency }}"
                                    @selected((string) $selectedClientId === (string) $client->id)>
                                {{ $client->name }} ({{ $client->default_currency }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="lcb-field" style="min-width: 10rem; flex: 0 1 12rem;">
                    <label for="lcb-edit-currency-{{ $campaign->id }}" class="lcb-label">Currency</label>
                    <select name="billing_currency"
                            id="lcb-edit-currency-{{ $campaign->id }}"
                            class="lcb-select"
                            data-lcb-edit-currency>
                        @foreach ($currencies as $code)
                            <option value="{{ $code }}" @selected(strtoupper((string) $selectedCurrency) === $code)>
                                {{ \App\Support\CurrencyFormatter::optionLabel($code) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="lcb-btn-update">
                    <span class="material-symbols-outlined !text-base">save</span>
                    Save client
                </button>
            </form>

            @error('local_client_id')
                <div class="lcb-error !mt-0" role="alert">
                    <span class="material-symbols-outlined">error</span>
                    <span>{{ $message }}</span>
                </div>
            @enderror
            @error('billing_currency')
                <div class="lcb-error !mt-0" role="alert">
                    <span class="material-symbols-outlined">error</span>
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                document.querySelectorAll('[data-lcb-edit]').forEach(function (wrap) {
                    var clientSelect = wrap.querySelector('[data-lcb-edit-client]');
                    var currencySelect = wrap.querySelector('[data-lcb-edit-currency]');
                    if (!clientSelect || !currencySelect) return;
                    clientSelect.addEventListener('change', function () {
                        var opt = clientSelect.options[clientSelect.selectedIndex];
                        var currency = opt && opt.getAttribute('data-currency');
                        if (currency) {
                            currencySelect.value = currency;
                        }
                    });
                });
            </script>
        @endpush
    @endonce
@endif
