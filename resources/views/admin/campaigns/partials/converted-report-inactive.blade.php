@extends('admin.layout.general')

@section('title', 'Campaign report inactive')

@section('main-content')
    <div class="w-full max-w-3xl mx-auto content-card !p-6 sm:!p-8">
        <h2 class="text-lg font-semibold text-gray-900 !mb-3">This campaign report is inactive</h2>
        <p class="text-sm text-gray-700">
            The live report for <strong>{{ $sourceCampaignNo }}</strong> was converted to a scheduled campaign.
            Old Live / Not Live rows are no longer the source of truth.
        </p>
        @if (filled($targetCampaignNo ?? null))
            <p class="text-sm text-gray-700 !mt-3">
                The converted campaign <strong>{{ $targetCampaignNo }}</strong> is no longer available.
            </p>
        @else
            <p class="text-sm text-gray-700 !mt-3">
                The converted campaign is no longer available.
            </p>
        @endif
    </div>
@endsection
