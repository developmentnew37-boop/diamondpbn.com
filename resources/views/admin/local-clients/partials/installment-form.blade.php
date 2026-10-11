@if ((float) ($openBalance['open_balance'] ?? 0) > 0)
    <button type="button" class="lc-theme-btn !min-h-[36px] !py-2 !px-3 !text-xs" data-lc-installment-open>
        <span class="material-symbols-outlined !text-sm">savings</span>
        Record installment
    </button>
@endif
