@php
    $manualBillingConfig = [
        'previewUrl' => route('admin.local-clients.manual-billing.preview', $localClient),
        'openOnError' => $errors->hasAny(['campaign_no', 'campaign_type', 'payment_status', 'post_links', 'keyword', 'url']),
    ];
    $manualBillingTypes = [
        'post' => 'Post',
        'sidebar' => 'Sidebar',
        'hidden_links' => 'Hidden Links',
        'sticky' => 'Sticky',
        'schedule_post' => 'Schedule Post',
        'schedule_sidebar' => 'Schedule Blogroll',
        'schedule_sticky' => 'Schedule Sticky',
    ];
@endphp

<div id="lc-manual-billing-modal" class="lc-modal" hidden aria-hidden="true">
    <div class="lc-modal-backdrop" data-lc-manual-billing-close tabindex="-1"></div>
    <div class="lc-modal-dialog lc-modal-dialog-form" role="dialog" aria-modal="true" aria-labelledby="lc-manual-billing-title">
        <div class="lc-modal-header">
            <h3 id="lc-manual-billing-title">Manual billing</h3>
            <button type="button" class="lc-modal-close" data-lc-manual-billing-close aria-label="Close">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.local-clients.manual-billings.store', $localClient) }}" id="lc-manual-billing-form">
            @csrf
            <div class="lc-modal-body">
                <p class="lc-help-text !mb-4">
                    Each line is one billable slot. Post quantity follows the live box. Keyword and URL lines must match that count, in the same order. The price is the sum of this client’s domain rates, including a repeated domain.
                </p>
                <div class="lc-manual-columns">
                    <div>
                        <div>
                            <label for="lc-manual-campaign-no" class="lc-modal-label">Campaign name</label>
                            <input type="text"
                                id="lc-manual-campaign-no"
                                name="campaign_no"
                                value="{{ old('campaign_no') }}"
                                maxlength="191"
                                required
                                class="lc-modal-input"
                                placeholder="Campaign name">
                            @error('campaign_no')
                                <p class="lc-modal-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="!mt-3">
                            <label for="lc-manual-campaign-type" class="lc-modal-label">Campaign type</label>
                            <select id="lc-manual-campaign-type" name="campaign_type" class="lc-modal-input" required>
                                @foreach ($manualBillingTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('campaign_type', 'post') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('campaign_type')
                                <p class="lc-modal-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="!mt-3">
                            <label for="lc-manual-payment-status" class="lc-modal-label">Payment status</label>
                            <select id="lc-manual-payment-status" name="payment_status" class="lc-modal-input" required>
                                <option value="unpaid" @selected(old('payment_status', 'unpaid') === 'unpaid')>Unpaid</option>
                                <option value="paid" @selected(old('payment_status') === 'paid')>Paid</option>
                            </select>
                            @error('payment_status')
                                <p class="lc-modal-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="!mt-3">
                            <label for="lc-manual-post-quantity" class="lc-modal-label">Post quantity</label>
                            <input type="text"
                                id="lc-manual-post-quantity"
                                value="0"
                                readonly
                                class="lc-modal-input"
                                aria-readonly="true">
                        </div>
                        <div class="lc-modal-balance !mt-3">
                            <span>Calculated total</span>
                            <strong id="lc-manual-total">—</strong>
                        </div>
                        <p id="lc-manual-total-error" class="lc-modal-error" hidden></p>
                        <p id="lc-manual-match" class="lc-modal-error" hidden></p>
                        <ul id="lc-manual-lines" class="lc-manual-lines"></ul>
                    </div>
                    <div>
                        <div id="lc-manual-post-links-wrap">
                            <label id="lc-manual-live-label" for="lc-manual-post-links" class="lc-modal-label">Live post links</label>
                            <p id="lc-manual-link-count" class="lc-manual-count">0 links</p>
                            <textarea id="lc-manual-post-links"
                                name="post_links"
                                rows="6"
                                maxlength="50000"
                                required
                                class="lc-modal-input"
                                placeholder="One link or domain per line">{{ old('post_links') }}</textarea>
                            @error('post_links')
                                <p class="lc-modal-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="!mt-3">
                            <label for="lc-manual-keyword" class="lc-modal-label">Keywords</label>
                            <p id="lc-manual-keyword-count" class="lc-manual-count">0 of 0 keywords</p>
                            <textarea id="lc-manual-keyword"
                                name="keyword"
                                rows="6"
                                maxlength="50000"
                                required
                                class="lc-modal-input"
                                placeholder="One per line">{{ old('keyword') }}</textarea>
                            @error('keyword')
                                <p class="lc-modal-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="!mt-3">
                            <label for="lc-manual-url" class="lc-modal-label">URLs</label>
                            <p id="lc-manual-url-count" class="lc-manual-count">0 of 0 URLs</p>
                            <textarea id="lc-manual-url"
                                name="url"
                                rows="6"
                                maxlength="50000"
                                required
                                class="lc-modal-input"
                                placeholder="One per line">{{ old('url') }}</textarea>
                            @error('url')
                                <p class="lc-modal-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
            <div class="lc-modal-footer">
                <button type="button" class="lc-btn-secondary !min-h-[40px]" data-lc-manual-billing-close>Cancel</button>
                <button type="submit" id="lc-manual-save" class="lc-theme-btn !min-h-[40px]" disabled>
                    <span class="material-symbols-outlined !text-base">receipt_long</span>
                    Save bill
                </button>
            </div>
        </form>
    </div>
</div>

<script type="application/json" id="lc-manual-billing-config">@json($manualBillingConfig)</script>
