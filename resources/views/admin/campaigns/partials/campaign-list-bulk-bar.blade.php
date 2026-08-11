@php
    $purgeFormId = $purgeFormId ?? 'campaign-bulk-purge-local-form';
    $purgeBtnId = $purgeBtnId ?? 'campaign-bulk-purge-local-btn';
    $retryFormId = $retryFormId ?? 'campaign-bulk-retry-failed-form';
    $retryBtnId = $retryBtnId ?? 'campaign-bulk-retry-failed-btn';
    $purgeAction = $purgeAction ?? '#';
    $retryAction = $retryAction ?? '#';
    $retryLabel = $retryLabel ?? 'Posts';
    $purgeTitle = $purgeTitle ?? 'Remove selected campaigns from this app only';
    $retryTitle = $retryTitle ?? 'Retry all failed items in selected campaigns';
    $helpText = $helpText ?? 'Select campaigns with checkboxes, then retry failed ' . strtolower($retryLabel) . ' or remove local records.';
@endphp

<form id="{{ $purgeFormId }}" action="{{ $purgeAction }}" method="POST" class="hidden">@csrf</form>
<form id="{{ $retryFormId }}" action="{{ $retryAction }}" method="POST" class="hidden">@csrf</form>
<div class="w-full flex flex-col gap-2 !mb-2">
    <div class="w-full flex flex-wrap items-center gap-2">
        <button type="button" id="{{ $purgeBtnId }}"
            class="!px-3 !py-2 rounded bg-orange-600 text-white text-sm hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed"
            title="{{ $purgeTitle }}">
            Bulk remove locally only
        </button>
        <button type="button" id="{{ $retryBtnId }}"
            class="!px-3 !py-2 rounded bg-blue-600 text-white text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            title="{{ $retryTitle }}">
            Bulk Retry Failed {{ $retryLabel }}
        </button>
    </div>
    <p class="text-sm text-gray-500">{{ $helpText }}</p>
</div>
