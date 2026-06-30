@if (empty($statusOnly))
    @if (isset($existingInventoryNames[$domain->normalized_name]))
        <span class="pd-badge pd-badge-inventory">In inventory</span>
    @else
        <span class="pd-badge pd-badge-new-site">New site</span>
    @endif
@endif

@if (!empty($statusOnly) || empty($viewedOnly))
    @if ($domain->status === 'pending')
        <span class="pd-badge pd-badge-pending">Pending</span>
    @elseif($domain->status === 'approved')
        <span class="pd-badge pd-badge-approved">Approved</span>
    @else
        <span class="pd-badge pd-badge-rejected">Rejected</span>
    @endif
@endif

@if (empty($statusOnly) && empty($viewedOnly))
    @if ($domain->viewed)
        <span class="pd-badge pd-badge-viewed">Viewed</span>
    @else
        <span class="pd-badge pd-badge-new">Unread</span>
    @endif
@endif
