@extends('admin.layout.layout')

@section('title', 'Convert sidebar campaign')

@push('style')
    @include('admin.campaigns.convert-sidebar.partials.convert-wizard-styles')
    <style>
        #convert-step1-form { overflow-x: clip; }
        #convert-step1-form [data-convert-campaign-picker] { overflow: visible; }
        #convert-step1-form [data-convert-campaign-picker]:focus-within { z-index: 40; }
        #convert-step1-form [data-convert-campaign-menu] { z-index: 50; max-width: 100%; }
        .convert-primary-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 42px; padding: 0.5rem 1.25rem; border-radius: 0.375rem;
            background: var(--primary-color); color: #fff; border: 0; cursor: pointer;
        }
        .convert-primary-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .convert-campaign-option.is-disabled { opacity: 0.55; cursor: not-allowed; }
        .convert-campaign-option-meta { display: block; font-size: 0.6875rem; color: #6b7280; margin-top: 0.15rem; }
        .convert-step1-divider {
            display: flex; align-items: center; gap: 0.75rem; margin: 1.25rem 0;
            color: #9ca3af; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .convert-step1-divider::before, .convert-step1-divider::after {
            content: ''; flex: 1; height: 1px; background: #e5e7eb;
        }
        .convert-report-url-result {
            margin-top: 0.75rem; padding: 0.875rem 1rem; border-radius: 0.375rem;
            border: 1px solid #e5e7eb; font-size: 0.875rem;
        }
        .convert-report-url-result.is-success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .convert-report-url-result.is-error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .convert-report-url-result.is-warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    </style>
@endpush

@section('main-content')
    <div class="page-header w-full">
        <div class="flex flex-col gap-2">
            <h2 class="page-title">Convert sidebar campaign</h2>
            <div class="breadcrumb">
                <div class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a><span>›</span></div>
                <div class="breadcrumb-item">Convert sidebar</div>
            </div>
        </div>
    </div>

    <div class="content-card w-full">
        @include('admin.campaigns.convert-sidebar.partials.stepper', ['currentStep' => 1])

        <p class="text-sm text-gray-600 !mb-4">
            Convert an existing live sidebar/blogroll campaign to a scheduled sidebar campaign without creating new blogroll entries on WordPress.
        </p>

        <div id="convert-step1-form" class="w-full flex flex-col gap-3 max-w-3xl">
            <label for="selected_campaign_id" class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                Select campaign
            </label>

            <div data-convert-campaign-picker class="w-full flex flex-col gap-3 p-2 relative" id="convert_campaign_picker">
                <input type="hidden" id="selected_campaign_id" value="{{ $preselectedId ?? '' }}">
                <button data-convert-campaign-btn type="button"
                    class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between focus:border-orange-600 transition-all">
                    <span data-convert-campaign-label class="text-gray-400">Select campaign ...</span>
                    <svg data-convert-campaign-chevron class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div data-convert-campaign-menu class="hidden absolute z-50 w-full top-full !mt-2 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden">
                    <div class="!p-3 border-b border-gray-200">
                        <div class="relative">
                            <input data-convert-campaign-search type="text"
                                class="w-full !pl-10 !pr-4 !py-2 bg-gray-100 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)] text-sm"
                                placeholder="Search campaign #...">
                        </div>
                    </div>
                    <div class="!px-3 !py-2 text-xs text-gray-500 border-b border-gray-100 bg-gray-50">
                        Recent sidebar campaigns (newest first). Type 2+ characters to search all.
                    </div>
                    <div data-convert-campaign-list class="max-h-64 overflow-y-auto"></div>
                    <div data-convert-campaign-loader class="hidden !px-4 !py-3 text-sm text-gray-500 border-t border-gray-100">Loading…</div>
                    <div data-convert-campaign-sentinel class="h-1"></div>
                </div>
            </div>

            <p class="text-xs text-gray-500">Choose a live sidebar campaign that is not already converted and has at least one published blogroll task.</p>

            <div class="convert-step1-divider">Or paste report URL</div>
            <label for="convert_report_url" class="convert-wizard-field-label">Campaign report URL</label>
            <div class="flex flex-col sm:flex-row gap-2">
                <input type="url" id="convert_report_url" class="convert-wizard-input flex-1"
                    placeholder="https://yoursite.com/sidebar/campaign/report/campaign-no/token" autocomplete="off">
                <button type="button" id="btn_lookup_report_url" class="convert-wizard-btn-action shrink-0">
                    <span class="material-symbols-outlined !text-lg" aria-hidden="true">link</span>
                    Find campaign
                </button>
            </div>
            <p class="text-xs text-gray-500">Paste the sidebar campaign report link (live sidebar campaigns only).</p>
            <div id="convert_report_url_result" class="convert-report-url-result hidden" role="status" aria-live="polite"></div>
        </div>

        <div class="!mt-6 flex justify-end max-w-3xl">
            <button type="button" id="btn_next" class="convert-primary-btn" disabled>Next</button>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.convertSidebarWizard = {
            searchUrl: @json(route('admin.convert.sidebar.search')),
            reportLookupUrl: @json(route('admin.convert.sidebar.lookup-report-url')),
            csrfToken: @json(csrf_token()),
            step2Url: @json(url('/admin/convert/sidebar/step/2')),
            preselectedId: @json($preselectedId),
            preselectedCampaignNo: @json($preselectedCampaign?->campaign_no),
        };
    </script>
    <script src="{{ asset('js/convert-sidebar-campaign.js') }}?v={{ filemtime(public_path('js/convert-sidebar-campaign.js')) }}"></script>
@endpush
