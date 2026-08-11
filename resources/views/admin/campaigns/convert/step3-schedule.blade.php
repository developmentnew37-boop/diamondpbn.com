@extends('admin.layout.layout')

@section('title', 'Schedule dates')

@push('style')
    @include('admin.campaigns.convert.partials.convert-wizard-styles')
@endpush

@section('main-content')
    <div class="page-header w-full">
        <div class="flex flex-col gap-2">
            <h2 class="page-title">Convert post campaign</h2>
            <div class="breadcrumb">
                <div class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                    <span>›</span>
                </div>
                <div class="breadcrumb-item">Schedule dates</div>
            </div>
        </div>
    </div>

    <div class="content-card w-full">
        @include('admin.campaigns.convert.partials.stepper', ['currentStep' => 3])

        <p class="text-sm text-gray-600 !mb-4">
            Campaign: <strong class="text-gray-900">{{ $campaign->campaign_no }}</strong>
        </p>

        <div class="w-full max-w-full">
            <div class="convert-wizard-summary !mb-5" id="schedule_summary">
                Scheduling <strong>{{ $summary['convertible'] }}</strong> posts — the date grid total must equal this count.
            </div>

            <form method="post" action="{{ route('admin.convert.post.step4', $campaign->id) }}" id="schedule_form">
                @csrf
                <input type="hidden" name="conversion_mode" value="{{ $conversionMode }}">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 !mb-5 sm:max-w-2xl">
                    <div>
                        <label class="convert-wizard-field-label" for="schedule_from_date">From date</label>
                        <input type="date" name="schedule_from_date" id="schedule_from_date"
                            class="convert-wizard-input" required>
                    </div>
                    <div>
                        <label class="convert-wizard-field-label" for="schedule_to_date">To date</label>
                        <input type="date" name="schedule_to_date" id="schedule_to_date"
                            class="convert-wizard-input" required>
                    </div>
                </div>

                <div class="!mb-5">
                    <button type="button" id="btn_build_grid" class="convert-wizard-btn-action">
                        <span class="material-symbols-outlined !text-lg" aria-hidden="true">calendar_view_month</span>
                        Build date grid
                    </button>
                    <p class="text-xs text-gray-500 !mt-2">Choose a date range, then build the grid. Adjust quantities per day if needed.</p>
                </div>

                <div class="overflow-x-auto !mb-3 rounded-lg border border-gray-200">
                    <table class="convert-wizard-table" id="date_grid">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <p class="convert-wizard-hint !mb-2" id="past_hint"></p>
                <input type="hidden" name="date_quantities" id="date_quantities" value="">

                <div class="convert-wizard-actions">
                    <a href="{{ route('admin.convert.post.step2', $campaign->id) }}" class="convert-wizard-btn-secondary">
                        <span class="material-symbols-outlined !text-lg" aria-hidden="true">arrow_back</span>
                        Back
                    </a>
                    <button type="submit" class="convert-wizard-btn-primary" id="btn_submit_schedule">
                        Next
                        <span class="material-symbols-outlined !text-lg" aria-hidden="true">arrow_forward</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.convertScheduleStep = {
            convertible: {{ (int) $summary['convertible'] }},
            today: @json(now()->toDateString()),
        };
    </script>
    <script src="{{ asset('js/convert-post-campaign.js') }}?v={{ @filemtime(public_path('js/convert-post-campaign.js')) ?: 1 }}"></script>
@endpush
