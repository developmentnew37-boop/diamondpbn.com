@if (
    Auth::guard('admin')->user()?->isSuperAdmin()
    && ! $period['is_open']
    && ! empty($period['paid_at'])
    && ! empty($period['unpaid_candidates'])
)
    @php
        $unpaidModalId = 'lc-unpaid-'.md5((string) ($period['id'] ?? 'x').'|'.$period['paid_at']->format('Y-m-d H:i:s'));
    @endphp
    <button type="button"
        class="lc-btn-secondary !min-h-[36px] !py-2 !px-3 !text-xs"
        data-lc-unpaid-open="{{ $unpaidModalId }}">
        <span class="material-symbols-outlined !text-sm">undo</span>
        Mark unpaid
    </button>
@endif
