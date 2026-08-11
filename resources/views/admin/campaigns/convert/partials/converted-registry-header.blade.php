{{--
    Registry panel header + bulk toolbar for converted dripfeed list.
    Expects: $campaigns (LengthAwarePaginator), bulk form routes via standard button ids.
--}}
@php
    $total = $campaigns->total();
    $hasRows = $total > 0;
@endphp

<div class="converted-registry-head">
    <div class="converted-registry-head-text">
        <div class="converted-registry-title-row">
            <span class="material-symbols-outlined converted-registry-title-icon" aria-hidden="true">event_repeat</span>
            <h3 class="converted-registry-title">Live → Dripfeed campaigns</h3>
        </div>
        <p class="converted-registry-subtitle">
            Live post campaigns moved to staggered dripfeed. Links stay hidden while WordPress status is draft or scheduled (<code>future</code>).
        </p>
    </div>
    @if ($hasRows)
        <span class="converted-registry-meta">
            Showing {{ $campaigns->firstItem() }}–{{ $campaigns->lastItem() }} of {{ number_format($total) }}
        </span>
    @endif
</div>

<form id="schedule-bulk-purge-local-form" action="{{ route('admin.schedule.campaign.bulk.purge.local') }}" method="POST" class="hidden">@csrf</form>
<form id="schedule-bulk-retry-failed-form" action="{{ route('admin.schedule.campaign.bulk.retry.failed') }}" method="POST" class="hidden">@csrf</form>

<div class="converted-registry-toolbar" role="toolbar" aria-label="Bulk campaign actions">
    <div class="converted-registry-toolbar-left">
        <span class="converted-registry-selected-pill" id="converted-bulk-selected-label" aria-live="polite">
            <span class="material-symbols-outlined !text-base" aria-hidden="true">checklist</span>
            <span id="converted-bulk-selected-count">0 selected</span>
        </span>
        <span class="converted-registry-toolbar-hint hidden sm:inline">Use row checkboxes or the header checkbox to select campaigns.</span>
    </div>
    <div class="converted-registry-toolbar-actions">
        <button type="button" id="schedule-bulk-retry-failed-btn"
            class="converted-registry-btn converted-registry-btn-primary"
            title="Retry all failed posts in selected campaigns"
            disabled>
            <span class="material-symbols-outlined !text-lg" aria-hidden="true">replay</span>
            Retry failed posts
        </button>
        <button type="button" id="schedule-bulk-purge-local-btn"
            class="converted-registry-btn converted-registry-btn-muted"
            title="Remove selected from this app only; remote posts stay published"
            disabled>
            <span class="material-symbols-outlined !text-lg" aria-hidden="true">inventory_2</span>
            Remove locally only
        </button>
    </div>
</div>
