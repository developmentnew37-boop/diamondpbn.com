@if ($installment->source === 'payment' && ! empty($reversibleIds[(int) $installment->id]) && $installment->localClient)
    <form method="POST"
        action="{{ route('admin.local-clients.installments.void-latest', $installment->localClient) }}"
        data-lc-once
        data-lc-confirm="Revert this installment and restore those campaigns?">
        @csrf
        @method('DELETE')
        <input type="hidden" name="installment_id" value="{{ $installment->id }}">
        <button type="submit" class="lc-btn-danger !min-h-[36px] !py-2 !px-3 !text-xs">
            <span class="material-symbols-outlined !text-sm">undo</span>
            Revert
        </button>
    </form>
@endif
