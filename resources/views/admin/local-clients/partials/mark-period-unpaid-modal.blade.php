@if (
    Auth::guard('admin')->user()?->isSuperAdmin()
    && ! $period['is_open']
    && ! empty($period['paid_at'])
    && ! empty($period['unpaid_candidates'])
)
    @php
        $unpaidModalId = 'lc-unpaid-'.md5((string) ($period['id'] ?? 'x').'|'.$period['paid_at']->format('Y-m-d H:i:s'));
        $openUnpaidModal = $errors->hasAny(['campaigns', 'billing_payment_status'])
            && old('paid_at') === $period['paid_at']->format('Y-m-d H:i:s');
    @endphp
    <div id="{{ $unpaidModalId }}" class="lc-modal" @if (! $openUnpaidModal) hidden @endif aria-hidden="{{ $openUnpaidModal ? 'false' : 'true' }}">
        <div class="lc-modal-backdrop" data-lc-unpaid-close tabindex="-1"></div>
        <div class="lc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $unpaidModalId }}-title">
            <div class="lc-modal-header">
                <h3 id="{{ $unpaidModalId }}-title">Mark unpaid</h3>
                <button type="button" class="lc-modal-close" data-lc-unpaid-close aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST"
                action="{{ route('admin.local-clients.mark-period-unpaid', $localClient) }}"
                data-lc-once
                data-lc-confirm="Mark the selected campaigns as unpaid?">
                @csrf
                <input type="hidden" name="paid_at" value="{{ $period['paid_at']->format('Y-m-d H:i:s') }}">
                <div class="lc-modal-body">
                    <p class="lc-help-text !mb-3">Choose one or more campaigns in this period. Campaigns you leave unchecked stay paid.</p>
                    @error('campaigns')
                        <p class="lc-modal-error">{{ $message }}</p>
                    @enderror
                    @error('billing_payment_status')
                        <p class="lc-modal-error">{{ $message }}</p>
                    @enderror
                    <input type="search"
                        data-lc-unpaid-search
                        placeholder="Search campaign number or type"
                        autocomplete="off"
                        class="lc-modal-input !mb-2">
                    <p class="text-xs text-gray-500 !mb-3" data-lc-unpaid-count>Selected 0 of {{ count($period['unpaid_candidates']) }}</p>
                    <label class="flex items-center gap-2 !mb-3 text-sm font-medium text-gray-800">
                        <input type="checkbox" data-lc-unpaid-all>
                        Select all
                    </label>
                    <div class="flex flex-col gap-2" style="max-height: 18rem; overflow: auto;">
                        @foreach ($period['unpaid_candidates'] as $row)
                            <label class="flex items-start gap-2 text-sm text-gray-800"
                                data-lc-unpaid-row
                                data-lc-unpaid-match="{{ mb_strtolower(trim(($row['campaign_no'] ?? '').' '.($row['type_label'] ?? ''))) }}">
                                <input type="checkbox"
                                    name="campaigns[]"
                                    value="{{ $row['selection_key'] }}"
                                    class="!mt-1"
                                    @checked($openUnpaidModal && in_array($row['selection_key'], (array) old('campaigns', []), true))>
                                <span>
                                    <strong>{{ $row['campaign_no'] }}</strong>
                                    · {{ $row['type_label'] }}
                                    · {{ \App\Support\CurrencyFormatter::format($row['billing_total'], $row['billing_currency'] ?? $localClient->default_currency) }}
                                    @if (! empty($row['installment_formatted']))
                                        <span class="block text-xs text-gray-500 !mt-0.5">
                                            Installment {{ $row['installment_formatted'] }} stays received and moves onto the other unpaid campaigns.
                                        </span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="lc-modal-footer">
                    <button type="button" class="lc-btn-secondary !min-h-[40px]" data-lc-unpaid-close>Cancel</button>
                    <button type="submit" class="lc-theme-btn !min-h-[40px]">
                        <span data-lc-once-label>Mark unpaid</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
