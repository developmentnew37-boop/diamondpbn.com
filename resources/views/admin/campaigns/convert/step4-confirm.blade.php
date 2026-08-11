@extends('admin.layout.layout')

@section('title', 'Confirm conversion')

@push('style')
    @include('admin.campaigns.convert.partials.convert-wizard-styles')
@endpush

@section('main-content')
    <div class="page-header w-full">
        <h2 class="page-title">Confirm & convert</h2>
    </div>

    <div class="content-card w-full">
        @include('admin.campaigns.convert.partials.stepper', ['currentStep' => 4])

        <p class="!mb-2">Source: <strong>{{ $campaign->campaign_no }}</strong></p>
        <p class="text-sm !mb-4">Mode: {{ $conversionMode }} · Posts: {{ $summary['convertible'] }}</p>

        <div id="preflight_table" class="!mb-4 text-sm">Running domain pre-flight…</div>

        <form method="post" action="{{ route('admin.convert.post.store') }}" id="convert_form">
            @csrf
            <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">
            <input type="hidden" name="conversion_mode" value="{{ $conversionMode }}">
            <input type="hidden" name="date_quantities" value="{{ json_encode($dateQuantities) }}">
            <input type="hidden" name="exchange_mappings" value="[]">

            <label class="block text-sm !mb-2" for="campaign_no">New dripfeed campaign #</label>
            <input type="text" name="campaign_no" id="campaign_no" class="convert-wizard-input !mb-4"
                   value="{{ $campaign->campaign_no }}-drip" required>

            <label class="flex items-start gap-2 text-sm !mb-4">
                <input type="checkbox" name="acknowledge_risk" value="1">
                <span>Domains with issues may fail individual posts; I will use Retry on the converted campaign when sites recover.</span>
            </label>

            <div class="convert-wizard-actions">
                <a href="{{ route('admin.convert.post.step3', ['campaign' => $campaign->id, 'mode' => $conversionMode]) }}"
                   class="convert-wizard-btn-secondary">
                    <span class="material-symbols-outlined !text-lg" aria-hidden="true">arrow_back</span>
                    Back
                </a>
                <button type="submit" class="convert-wizard-btn-primary">Convert</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (async function () {
            const url = @json(route('admin.convert.post.preflight.campaign', $campaign->id));
            const el = document.getElementById('preflight_table');
            try {
                const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
                const j = await r.json();
                const rows = (j.domains || []).map(d =>
                    `<tr><td class="!p-2 border">${d.domain_name}</td><td class="!p-2 border">${d.ok ? 'OK' : 'Warn'}</td><td class="!p-2 border">${d.agent_version || '-'}</td><td class="!p-2 border text-xs">${d.message}</td></tr>`
                ).join('');
                el.innerHTML = `<p class="!mb-2">Pre-flight (warn only — conversion is not blocked): ${j.offline_domain_count ?? 0} domain(s) need attention.</p>
                    <table class="w-full border-collapse"><thead><tr><th class="!p-2 border">Domain</th><th class="!p-2 border">Status</th><th class="!p-2 border">Agent</th><th class="!p-2 border">Message</th></tr></thead><tbody>${rows}</tbody></table>`;
            } catch (e) {
                el.textContent = 'Pre-flight could not be loaded.';
            }
        })();
    </script>
@endpush
