@php
    $viewUrl = $viewUrl ?? '#';
    $editUrl = $editUrl ?? null;
    $reportUrl = $reportUrl ?? '#';
    $reportCopyUrl = $reportCopyUrl ?? $reportUrl;
    $destroyAction = $destroyAction ?? null;
    $destroyMethod = $destroyMethod ?? 'DELETE';
    $purgeAction = $purgeAction ?? null;
    $destroyConfirm = $destroyConfirm ?? 'Delete this campaign?';
    $purgeConfirm = $purgeConfirm ?? 'Remove this campaign from the dashboard only? Remote content will stay.';
    $openReportInNewTab = $openReportInNewTab ?? false;
    $hasReport = ! empty($reportUrl) && $reportUrl !== '#';
@endphp

<div class="flex flex-nowrap items-center justify-center gap-2">
    <a href="{{ $viewUrl }}"
        class="bg-green-500 flex shrink-0 items-center justify-center rounded w-7 h-7 hover:bg-green-600"
        title="View campaign">
        <span class="material-symbols-outlined !text-sm text-white">visibility</span>
    </a>

    @if ($hasReport)
        <a href="javascript:void(0)"
            data-report="{{ $reportCopyUrl }}"
            class="bg-yellow-500 copy-link flex shrink-0 items-center justify-center rounded w-7 h-7 hover:bg-yellow-600"
            title="Copy report link">
            <span class="material-symbols-outlined !text-sm text-white">content_copy</span>
        </a>

        <a href="{{ $reportUrl }}"
            @if ($openReportInNewTab) target="_blank" @endif
            class="bg-blue-700 flex shrink-0 items-center justify-center rounded w-7 h-7 hover:bg-blue-800"
            title="View report">
            <span class="material-symbols-outlined !text-sm text-white">assignment</span>
        </a>
    @endif

    @if ($editUrl)
        <a href="{{ $editUrl }}"
            class="bg-gray-700 flex shrink-0 items-center justify-center rounded w-7 h-7 duration-500 hover:bg-gray-700"
            title="Edit campaign">
            <span class="material-symbols-outlined !text-[16px] text-white">edit_square</span>
        </a>
    @endif

    @if ($destroyAction)
        <form action="{{ $destroyAction }}" method="POST" class="inline-flex shrink-0"
            onsubmit="return confirm(@json($destroyConfirm));">
            @csrf
            @if (strtoupper($destroyMethod) !== 'POST')
                @method($destroyMethod)
            @endif
            @if (!empty($destroyHiddenInputs))
                @foreach ($destroyHiddenInputs as $input)
                    <input type="hidden" name="{{ $input['name'] }}" value="{{ $input['value'] }}">
                @endforeach
            @endif
            <button type="submit"
                class="bg-red-500 flex items-center justify-center rounded w-7 h-7 hover:bg-red-600 border-0 cursor-pointer"
                title="Delete campaign">
                <span class="material-symbols-outlined !text-[16px] text-white">delete</span>
            </button>
        </form>
    @endif

    @if ($purgeAction)
        <form action="{{ $purgeAction }}" method="POST" class="inline-flex shrink-0"
            onsubmit="return confirm(@json($purgeConfirm));">
            @csrf
            <button type="submit"
                class="bg-orange-500 flex items-center justify-center rounded w-7 h-7 hover:bg-orange-600 border-0 cursor-pointer"
                title="Remove from dashboard only — does not delete remote content">
                <span class="material-symbols-outlined !text-[16px] text-white">database</span>
            </button>
        </form>
    @endif
</div>
