<div class="flex gap-2 flex-wrap">
    <a href="{{ route('admin.pending-domains.show', $domain->id) }}"
        title="View details"
        class="bg-green-500 flex items-center justify-center rounded w-8 h-8 duration-300 hover:bg-green-600 shrink-0">
        <span class="material-symbols-outlined !text-sm text-white">visibility</span>
    </a>

    @if ($domain->status === 'pending')
        <a href="{{ route('admin.transfer-domains.step2', ['domain_ids' => [$domain->id]]) }}"
            title="Transfer to domains"
            class="bg-[var(--sidebar-bg)] flex items-center justify-center rounded w-8 h-8 duration-300 hover:bg-[var(--primary-color)] shrink-0">
            <span class="material-symbols-outlined !text-sm text-white">swap_horiz</span>
        </a>

        <button type="button"
            title="Reject domain"
            class="bg-yellow-500 flex items-center justify-center rounded w-8 h-8 duration-300 hover:bg-yellow-600 shrink-0"
            onclick="showRejectModal({{ $domain->id }}, '{{ addslashes($domain->domain_name) }}')">
            <span class="material-symbols-outlined !text-sm text-white">block</span>
        </button>
    @endif

    <form action="{{ route('admin.pending-domains.destroy', $domain->id) }}"
        method="POST" class="inline shrink-0"
        onsubmit="return confirm('Delete this pending domain record?')">
        @csrf
        @method('DELETE')
        <button type="submit"
            title="Delete record"
            class="bg-red-600 flex items-center justify-center rounded w-8 h-8 duration-300 hover:bg-red-700 cursor-pointer">
            <span class="material-symbols-outlined !text-sm text-white">delete</span>
        </button>
    </form>
</div>
