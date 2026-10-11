@if ((float) ($openBalance['open_balance'] ?? 0) > 0)
    <div id="lc-installment-modal" class="lc-modal" hidden aria-hidden="true">
        <div class="lc-modal-backdrop" data-lc-installment-close tabindex="-1"></div>
        <div class="lc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="lc-installment-title">
            <div class="lc-modal-header">
                <h3 id="lc-installment-title">Record installment</h3>
                <button type="button" class="lc-modal-close" data-lc-installment-close aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST"
                action="{{ route('admin.local-clients.installments.store', $localClient) }}"
                data-lc-once
                data-lc-recording="Recording">
                @csrf
                <input type="hidden" name="form_token" value="{{ $installmentFormToken }}">
                <div class="lc-modal-body">
                    <p class="lc-help-text !mb-4">
                        This payment is applied to the oldest open campaigns. Fully covered campaigns become paid. The campaign where the money runs out becomes semi paid.
                    </p>
                    <div class="lc-modal-balance">
                        <span>Open {{ $openBalance['currency'] }} balance</span>
                        <strong>{{ \App\Support\CurrencyFormatter::format($openBalance['open_balance'], $openBalance['currency']) }}</strong>
                    </div>
                    @foreach ($openBalance['other_balances'] as $currency => $balance)
                        <p class="text-xs text-gray-500 !mt-2 !mb-0">
                            {{ $currency }} {{ \App\Support\CurrencyFormatter::format($balance, $currency) }} is not included in this payment.
                        </p>
                    @endforeach
                    <div class="!mt-4">
                        <label for="installment-amount" class="lc-modal-label">Amount</label>
                        <input type="number"
                            id="installment-amount"
                            name="amount"
                            step="0.01"
                            min="0.01"
                            max="{{ $openBalance['open_balance'] }}"
                            value="{{ old('amount') }}"
                            required
                            class="lc-modal-input"
                            placeholder="0.00">
                        @error('amount')
                            <p class="lc-modal-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="!mt-3">
                        <label for="installment-note" class="lc-modal-label">Note</label>
                        <input type="text"
                            id="installment-note"
                            name="note"
                            value="{{ old('note') }}"
                            maxlength="500"
                            class="lc-modal-input"
                            placeholder="Optional">
                        @error('note')
                            <p class="lc-modal-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="lc-modal-footer">
                    <button type="button" class="lc-btn-secondary !min-h-[40px]" data-lc-installment-close>Cancel</button>
                    <button type="submit" class="lc-theme-btn !min-h-[40px]">
                        <span class="material-symbols-outlined !text-base">savings</span>
                        <span data-lc-once-label>Record installment</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
