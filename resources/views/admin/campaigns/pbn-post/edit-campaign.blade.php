@extends('admin.layout.layout')

@section('title', 'Edit Campaign')

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.campaign.index') }}" class="breadcrumb-link">PBN Post Campaign</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="javascript:void(0)" class="breadcrumb-link">Edit {{ $campaign->campaign_no }}</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                <a href="javascript:void(0)" onclick="history.back()"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 duration-400 hover:bg-gray-300 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7M3 12h18" />
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </div>

    {{-- Campaign status: shows Updating... while saving, then Campaign is updated after success --}}
    <div class="w-full !mt-2 !mb-2" id="campaign-status-wrap">
        <div class="!px-4 !py-3 rounded-lg border border-gray-200 bg-gray-50 text-sm" id="campaign-status-box">
            <span class="text-gray-600">Campaign status:</span>
            <span id="campaign-status-text" class="font-medium ml-1">{{ ucfirst($campaign->status ?? '') }}</span>
        </div>
    </div>

    @if (session('cus__success') || session('cus__error'))
        <div class="w-full flex flex-col gap-2 !mt-2 !mb-2">
            @if (session('cus__success'))
                <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                    {{ session('cus__success') }}
                </div>
            @endif
            @if (session('cus__error'))
                <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                    {{ session('cus__error') }}
                </div>
            @endif
        </div>
    @endif

    <div class="w-full flex content-card !p-4">
        <div class="w-full !py-6 !px-2 text-lg flex flex-col justify-center gap-3">
            <div class="w-full flex flex-col justify-center !px-2 gap-2">
                <h2 class="text-xl font-medium text-white bg-[var(--primary-color)] !px-3 !py-2 w-fit rounded">
                    Update {{ $campaign->campaign_no }}</h2>
            </div>
            <form action="{{ route('admin.campaign.update', $campaign->id) }}" method="post">
                @csrf
                @method('PUT')
                <div class="w-full flex flex-col gap-1 !p-2 !mt-1">
                    <div class="w-full relative !mb-2">
                        <input type="text" name="campaign_no" value="{{ $campaign->campaign_no }}"
                            class="w-full rounded !p-3 text-sm outline-0 themeFont border border-gray-300 focus:border-[var(--primary-color)]">
                    </div>
                    @error('campaign_no')
                        <div class="w-full flex flex-wrap text-sm text-red-600 !mb-2"><span>{{ $message }}</span></div>
                    @enderror
                    <input type="submit" value="Update campaign no"
                        class="w-full max-w-[300px] !py-3 !mt-2 cursor-pointer px-2 rounded-lg bg-[var(--primary-color)] text-white capitalize">
                </div>
            </form>
        </div>
    </div>

    {{-- Distinct keyword/url batches: update one batch = update all posts sharing that pair --}}
    <form action="{{ route('admin.campaign.bulk.update', $campaign->id) }}" method="post" class="w-full !mt-4" id="bulk-keyword-form">
        @csrf
        <div class="w-full content-card flex flex-col gap-2 !p-4">
            <h2 class="text-xl w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded">
                Update keywords &amp; URLs (distinct batches)
            </h2>
            <p class="text-sm text-gray-600">
                Each batch can have one or more keyword/URL pairs (json type = multiple).
            </p>

            <div class="w-full overflow-x-auto !mt-4 flex flex-col gap-4">
                @foreach ($distinctBatches as $idx => $batch)
                    @php
                        $pairs = [];
                        if (($batch['keyword_type'] ?? '') === 'json') {
                            $kwDec = json_decode($batch['keyword'], true);
                            $urlDec = json_decode($batch['url'], true);
                            if (is_array($kwDec) && is_array($urlDec)) {
                                $n = min(count($kwDec), count($urlDec));
                                for ($i = 0; $i < $n; $i++) {
                                    $pairs[] = ['keyword' => $kwDec[$i] ?? '', 'url' => $urlDec[$i] ?? ''];
                                }
                            }
                        } else {
                            $pairs[] = ['keyword' => (string) ($batch['keyword'] ?? ''), 'url' => (string) ($batch['url'] ?? '')];
                        }
                    @endphp
                    <div class="border border-gray-200 rounded-lg !p-4 bg-gray-50">
                        <input type="hidden" name="batch_representative_id[]" value="{{ $batch['representative_id'] }}">
                        <div class="flex items-center justify-between !mb-2">
                            <span class="font-medium text-gray-700">Batch {{ $idx + 1 }}</span>
                            <span class="text-sm text-gray-500">{{ $batch['count'] }} post(s)</span>
                        </div>
                        <div class="flex flex-col gap-3 batch-rows" data-batch-index="{{ $idx }}">
                            @foreach ($pairs as $pairIdx => $pair)
                                <div class="flex flex-wrap gap-2 items-center batch-row">
                                    <input type="text" name="batch_keyword[{{ $idx }}][]" value="{{ $pair['keyword'] }}"
                                        class="flex-1 min-w-[120px] rounded !p-2 text-sm border border-gray-300 focus:border-[var(--primary-color)]"
                                        placeholder="Keyword">
                                    <input type="url" name="batch_url[{{ $idx }}][]" value="{{ $pair['url'] }}"
                                        class="flex-1 min-w-[180px] rounded !p-2 text-sm border border-gray-300 focus:border-[var(--primary-color)]"
                                        placeholder="https://...">
                                    <button type="button" class="remove-pair-btn !px-2 !py-2 rounded bg-red-100 text-red-700 hover:bg-red-200 text-sm">Remove</button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="add-pair-btn !mt-2 !px-3 !py-1.5 rounded bg-gray-200 hover:bg-gray-300 text-sm">+ Add row</button>
                    </div>
                @endforeach
            </div>

            @if (count($distinctBatches) > 0)
                <div class="w-full !mt-4">
                    <button type="submit" id="bulk-update-submit-btn" class="!px-4 !py-3 rounded-lg bg-[var(--primary-color)] text-white hover:opacity-90 disabled:opacity-70 disabled:cursor-not-allowed">
                        Update batches &amp; sync to remote posts
                    </button>
                </div>
            @else
                <p class="text-gray-500 !mt-2">No campaign articles found.</p>
            @endif
        </div>
    </form>

    @push('scripts')
    <script>
    (function() {
        var bulkForm = document.getElementById('bulk-keyword-form');
        var submitBtn = document.getElementById('bulk-update-submit-btn');
        var statusText = document.getElementById('campaign-status-text');
        var statusBox = document.getElementById('campaign-status-box');
        var normalStatus = "{{ ucfirst($campaign->status ?? '') }}";

        if (bulkForm && submitBtn && statusText) {
            bulkForm.addEventListener('submit', function() {
                statusText.textContent = 'Updating...';
                if (statusBox) {
                    statusBox.classList.add('!bg-amber-50', '!border-amber-300');
                    statusBox.classList.remove('bg-gray-50', 'border-gray-200');
                }
                submitBtn.disabled = true;
                submitBtn.textContent = 'Updating...';
            });
        }

        if (statusText && statusBox && document.querySelector('.bg-green-100')) {
            statusText.textContent = 'Campaign is updated';
            statusBox.classList.add('!bg-green-50', '!border-green-300');
            statusBox.classList.remove('bg-gray-50', 'border-gray-200', '!bg-amber-50', '!border-amber-300');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update batches & sync to remote posts';
            }
            setTimeout(function() {
                statusText.textContent = normalStatus;
                statusBox.classList.remove('!bg-green-50', '!border-green-300');
                statusBox.classList.add('bg-gray-50', 'border-gray-200');
            }, 5000);
        } else if (statusText && statusBox && submitBtn && document.querySelector('.bg-red-100')) {
            statusText.textContent = normalStatus;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Update batches & sync to remote posts';
        }

        document.querySelectorAll('.remove-pair-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var row = this.closest('.batch-row');
                var block = this.closest('.batch-rows');
                var rows = block.querySelectorAll('.batch-row');
                if (rows.length > 1) row.remove();
            });
        });
        document.querySelectorAll('.add-pair-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var block = this.previousElementSibling;
                if (!block || !block.classList.contains('batch-rows')) return;
                var firstRow = block.querySelector('.batch-row');
                if (!firstRow) return;
                var idx = block.getAttribute('data-batch-index');
                var newRow = firstRow.cloneNode(true);
                newRow.querySelector('input[name*="keyword"]').value = '';
                newRow.querySelector('input[name*="url"]').value = '';
                newRow.querySelector('input[name*="keyword"]').setAttribute('name', 'batch_keyword[' + idx + '][]');
                newRow.querySelector('input[name*="url"]').setAttribute('name', 'batch_url[' + idx + '][]');
                block.appendChild(newRow);
                newRow.querySelector('.remove-pair-btn').addEventListener('click', function() {
                    var r = this.closest('.batch-row');
                    if (block.querySelectorAll('.batch-row').length > 1) r.remove();
                });
            });
        });
    })();
    </script>
    @endpush

@endsection
