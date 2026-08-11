@if (! ($period['is_open'] ?? false))
    @php
        $periodDeleteConfirm = 'Delete this billing period record? Campaign payment status will not change — only this period entry is removed.';
        $periodDeleteAction = ! empty($period['id'])
            ? route('admin.local-clients.billing-periods.destroy', [$localClient, $period['id']])
            : route('admin.local-clients.billing-periods.destroy-by-paid-at', $localClient);
    @endphp
    <form method="POST"
        action="{{ $periodDeleteAction }}"
        class="inline"
        onsubmit="return confirm({{ json_encode($periodDeleteConfirm) }});">
        @csrf
        @method('DELETE')
        @if (empty($period['id']) && ! empty($period['paid_at']))
            <input type="hidden" name="paid_at" value="{{ $period['paid_at']->format('Y-m-d H:i:s') }}">
        @endif
        <button type="submit" class="lc-table-btn lc-table-btn-delete">
            <span class="material-symbols-outlined">delete</span>
            Delete
        </button>
    </form>
@endif
