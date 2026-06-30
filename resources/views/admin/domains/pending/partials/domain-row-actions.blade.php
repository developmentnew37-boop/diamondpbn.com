<div class="pd-action-group">
    <a href="{{ route('admin.pending-domains.show', $domain->id) }}"
        title="View details"
        class="pd-action-btn pd-action-view">
        <span class="material-symbols-outlined">visibility</span>
    </a>

    @if ($domain->status === 'pending')
        <a href="{{ route('admin.transfer-domains.step2', ['domain_ids' => [$domain->id]]) }}"
            title="Transfer to domains"
            class="pd-action-btn pd-action-transfer">
            <span class="material-symbols-outlined">swap_horiz</span>
        </a>

        <button type="button"
            title="Reject domain"
            class="pd-action-btn pd-action-reject"
            onclick="showRejectModal({{ $domain->id }}, '{{ addslashes($domain->domain_name) }}')">
            <span class="material-symbols-outlined">block</span>
        </button>
    @endif

    <form action="{{ route('admin.pending-domains.destroy', $domain->id) }}"
        method="POST" class="inline"
        onsubmit="return confirm('Delete this pending domain record?')">
        @csrf
        @method('DELETE')
        <button type="submit"
            title="Delete record"
            class="pd-action-btn pd-action-delete">
            <span class="material-symbols-outlined">delete</span>
        </button>
    </form>
</div>
