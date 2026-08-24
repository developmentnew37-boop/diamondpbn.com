@if (!empty($localClients) && $localClients->isNotEmpty())
    @php
        $currencies = \App\Support\CurrencyFormatter::supportedCodes();
        $lcbStorageKey = 'create-'.($billingCampaignType ?? 'post').(!empty($isStickyBilling) ? '-sticky' : '');
    @endphp

    @once
        @push('style')
            @include('admin.campaigns.partials.local-client-billing-styles')
        @endpush
        @push('scripts')
            <script src="{{ asset('js/local-client-billing-toggle.js') }}?v={{ filemtime(public_path('js/local-client-billing-toggle.js')) }}"></script>
        @endpush
    @endonce

    <div class="lcb-view-wrapper"
         data-lcb-view
         data-lcb-key="{{ $lcbStorageKey }}"
         data-lcb-show-label="Show estimate"
         data-lcb-hide-label="Hide estimate">
        <div class="lcb-view-bar">
            <div class="lcb-view-bar-left">
                <div class="lcb-view-bar-icon" aria-hidden="true">
                    <span class="material-symbols-outlined">payments</span>
                </div>
                <span class="lcb-view-bar-title">Local Client Billing</span>
                <span class="lcb-optional-badge">Optional</span>
            </div>

            <div class="lcb-view-bar-fields">
                <div class="lcb-field lcb-field-inline">
                    <label for="local_client_id" class="lcb-label">Client</label>
                    <select name="local_client_id" id="local_client_id" class="lcb-select lcb-select-compact">
                        <option value="">— No client —</option>
                        @foreach ($localClients as $client)
                            <option value="{{ $client->id }}" data-currency="{{ $client->default_currency }}">
                                {{ $client->name }} ({{ $client->default_currency }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="lcb-field lcb-field-inline">
                    <label for="billing_currency" class="lcb-label">Currency</label>
                    <select name="billing_currency" id="billing_currency" class="lcb-select lcb-select-compact">
                        @foreach ($currencies as $code)
                            <option value="{{ $code }}">{{ \App\Support\CurrencyFormatter::optionLabel($code) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="lcb-view-bar-actions">
                <button type="button"
                        class="lcb-toggle-btn"
                        data-lcb-toggle-btn
                        aria-expanded="false"
                        aria-controls="local-client-billing-panel">
                    <span data-lcb-toggle-label>Show estimate</span>
                    <span class="material-symbols-outlined" data-lcb-toggle-icon>expand_more</span>
                </button>
            </div>
        </div>

        <div class="lcb-panel is-collapsed"
             id="local-client-billing-panel"
             data-lcb-panel
             data-estimate-url-template="{{ url('/admin/local-clients/__ID__/estimate') }}"
             data-campaign-type="{{ $billingCampaignType ?? 'post' }}"
             data-is-sticky="{{ !empty($isStickyBilling) ? '1' : '0' }}">
            <p class="lcb-panel-desc !mb-3">
                Per-domain billing uses the client price matrix (or linked rate list) for the selected domains.
                Estimate appears after domains are resolved — for Manual Domains, after the list validates against your inventory.
                Currency defaults from the client profile and can be overridden above (no FX conversion).
            </p>

            <div id="local-client-billing-estimate" class="lcb-estimate hidden">
                <div class="lcb-estimate-total" id="local-client-billing-total"></div>
                <ul id="local-client-billing-lines" class="lcb-estimate-lines"></ul>
            </div>

            <div id="local-client-billing-error" class="lcb-error hidden" role="alert">
                <span class="material-symbols-outlined">error</span>
                <span id="local-client-billing-error-text"></span>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/local-client-billing.js') }}?v={{ filemtime(public_path('js/local-client-billing.js')) }}"></script>
    @endpush
@endif
