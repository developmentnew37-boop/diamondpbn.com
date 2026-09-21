@php
    $convertedCampaign = $convertedCampaign ?? $campaign->convertedScheduleCampaign ?? null;
    $isConverted = $convertedCampaign
        || filled($campaign->converted_to_schedule_campaign_id ?? null)
        || filled($campaign->converted_to_schedule_sidebar_campaign_id ?? null);
    $convertedShowRoute = $convertedShowRoute ?? null;
@endphp
@if ($isConverted)
    <div class="w-full !mt-3 !mb-1 !p-4 text-sm rounded bg-gray-100 border border-gray-300 text-gray-800">
        <strong>Converted.</strong>
        This live campaign is inactive.
        @if ($convertedCampaign)
            The report was shifted into
            <strong>{{ $convertedCampaign->campaign_no }}</strong>.
            @if ($convertedShowRoute)
                <a href="{{ $convertedShowRoute }}" class="underline text-[var(--primary-color)] font-semibold">
                    Open the converted campaign
                </a>
            @endif
        @else
            The converted campaign is no longer available.
        @endif
    </div>
@endif
