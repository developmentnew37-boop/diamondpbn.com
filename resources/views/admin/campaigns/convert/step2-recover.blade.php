@extends('admin.layout.layout')

@section('title', 'Recover failed posts')

@push('style')
    @include('admin.campaigns.convert.partials.convert-wizard-styles')
@endpush

@section('main-content')
    <div class="page-header w-full">
        <h2 class="page-title">Review & recover</h2>
    </div>

    <div class="content-card w-full">
        @include('admin.campaigns.convert.partials.stepper', ['currentStep' => 2])

        <p class="!mb-2">Campaign <strong>{{ $campaign->campaign_no }}</strong></p>
        <ul class="text-sm !mb-4">
            <li>Convertible: {{ $summary['convertible'] }}</li>
            <li>Failed: {{ $summary['failed'] }}</li>
            <li>In progress: {{ $summary['in_progress'] }}</li>
        </ul>

        @if ($summary['in_progress'] > 0)
            <div class="!p-3 rounded bg-yellow-100 text-yellow-800 !mb-4">
                Posts are still publishing. Wait for the queue worker, then refresh eligibility.
            </div>
        @endif

        <div class="flex flex-wrap gap-2 !mb-4">
            <button type="button" id="btn_retry" class="convert-primary-btn">Retry failed posts</button>
            <button type="button" id="btn_refresh" class="page-btn">Refresh counts</button>
        </div>
        <p class="text-xs text-gray-500 !mb-4">Ensure the queue worker is running on <code>bulk_retry</code> and <code>campaigns</code>.</p>

        <form method="get" action="{{ route('admin.convert.post.step3', $campaign->id) }}" id="recover_form">
            <fieldset class="!mb-4">
                <legend class="font-semibold !mb-2">If failures remain</legend>
                <label class="flex items-center gap-2 !mb-2">
                    <input type="radio" name="mode" value="partial" checked>
                    Convert live posts only (partial)
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="mode" value="exchange">
                    Exchange domain for failures, then convert all (confirm on step 4)
                </label>
            </fieldset>

            <div class="convert-wizard-actions">
                <a href="{{ route('admin.convert.post.step1') }}" class="convert-wizard-btn-secondary">
                    <span class="material-symbols-outlined !text-lg" aria-hidden="true">arrow_back</span>
                    Back
                </a>
                <button type="submit" class="convert-wizard-btn-primary" @disabled($summary['in_progress'] > 0)>Next</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        const eligibilityUrl = @json(route('admin.convert.post.eligibility', $campaign->id));
        const retryUrl = @json(route('admin.convert.post.retry-failed', $campaign->id));
        document.getElementById('btn_retry')?.addEventListener('click', () => {
            fetch(retryUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } });
            alert('Retry queued. Refresh counts in a few seconds.');
        });
        document.getElementById('btn_refresh')?.addEventListener('click', async () => {
            const r = await fetch(eligibilityUrl, { headers: { 'Accept': 'application/json' } });
            const j = await r.json();
            alert(`Convertible: ${j.convertible}, Failed: ${j.failed}, In progress: ${j.in_progress}`);
            if (j.in_progress === 0 && j.all_live) location.href = @json(route('admin.convert.post.step3', $campaign->id));
        });
    </script>
@endpush
