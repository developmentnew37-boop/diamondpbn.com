@php
    $convertedNotice = session(\App\Services\ConvertedCampaignReportRedirector::FLASH_KEY);
@endphp
@if (filled($convertedNotice))
    <div class="w-full !mb-4 !p-4 text-sm rounded bg-blue-50 text-blue-800 border border-blue-200" role="status">
        {{ $convertedNotice }}
    </div>
@endif
