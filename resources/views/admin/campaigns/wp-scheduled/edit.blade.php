@extends('admin.layout.layout')

@section('title', 'Edit WP Scheduled Campaign')

@push('style')
    <style>
        .sortable-ghost {
            opacity: 0.5;
        }
    </style>
@endpush

@section('main-content')

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
                        <a href="{{ route('admin.wp.schedule.campaign.index') }}" class="breadcrumb-link">WP Scheduled</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="javascript:void(0)" class="breadcrumb-link">Edit {{ $campaign->campaign_no }}</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center gap-2">
                <a href="{{ route('admin.wp.schedule.campaign.show', $campaign->id) }}"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 duration-400 hover:bg-gray-300 text-sm">
                    View campaign
                </a>
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

    @php
        $editTab = session('edit_wp_schedule_campaign_tab', 'campaign');
        if (! in_array($editTab, ['campaign', 'batch', 'multi'], true)) {
            $editTab = 'campaign';
        }
    @endphp

    <div class="w-full flex flex-wrap justify-between items-start content-card !p-4 !mt-2">
        <h2 class="text-xl capitalize !mb-2 bg-[var(--primary-color)] text-white w-full !p-2 rounded max-w-fit">
            Edit WP scheduled — {{ $campaign->campaign_no }}
        </h2>
        <div class="w-full flex flex-wrap gap-5 !p-2 !pb-0">
            <button type="button" data-wp-sched-edit-tab="campaign"
                class="wp-sched-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'campaign' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                <span>Campaign info</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'campaign' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'campaign' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'campaign' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                    </div>
                    <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $editTab === 'campaign' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                </div>
            </button>
            <button type="button" data-wp-sched-edit-tab="batch"
                class="wp-sched-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'batch' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                <span>Distinct batches</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'batch' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'batch' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'batch' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                    </div>
                    <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $editTab === 'batch' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                </div>
            </button>
            <button type="button" data-wp-sched-edit-tab="multi"
                class="wp-sched-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'multi' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                <span>Multi-level keywords</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'multi' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'multi' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'multi' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                    </div>
                    <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $editTab === 'multi' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                </div>
            </button>
        </div>

        <div id="wp-sched-edit-panel-campaign" data-wp-sched-edit-panel="campaign"
            class="wp-sched-edit-panel w-full !p-4 duration-500 transition-all {{ $editTab === 'campaign' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <div class="w-full flex flex-col gap-3 !px-2">
                <p class="text-sm text-gray-600">Update the campaign identifier shown in lists and reports.</p>
                <form action="{{ route('admin.wp.schedule.campaign.update', $campaign->id) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="w-full flex flex-col gap-1 !mt-1">
                        <label for="wp-sched-edit-campaign-no" class="text-sm text-gray-700">Campaign #</label>
                        <div class="w-full relative !mb-2">
                            <input id="wp-sched-edit-campaign-no" type="text" name="campaign_no" value="{{ $campaign->campaign_no }}"
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

        <div id="wp-sched-edit-panel-batch" data-wp-sched-edit-panel="batch"
            class="wp-sched-edit-panel w-full !p-4 duration-500 transition-all {{ $editTab === 'batch' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <form action="{{ route('admin.wp.schedule.campaign.bulk.update', $campaign->id) }}" method="post" class="w-full" id="bulk-keyword-form">
                @csrf
                <div class="w-full flex flex-col gap-2">
                    <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded">
                        Update keywords &amp; URLs (distinct batches)
                    </h3>
                    <p class="text-sm text-gray-600 max-w-4xl">
                        Each batch can have one or more keyword/URL pairs (json type = multiple). Changes are saved to the database and published posts are queued to update on the remote WordPress site.
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
                                Update batches (DB + remote)
                            </button>
                        </div>
                    @else
                        <p class="text-gray-500 !mt-2">No campaign articles found.</p>
                    @endif
                </div>
            </form>
        </div>

        <div id="wp-sched-edit-panel-multi" data-wp-sched-edit-panel="multi"
            class="wp-sched-edit-panel w-full !p-4 duration-500 transition-all {{ $editTab === 'multi' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded">
                Multi-level keyword &amp; URL (per post)
            </h3>
            <p class="text-sm text-gray-600 max-w-4xl !mt-2">
                Post quantity: <strong>{{ (int) $postQuantity }}</strong>.
                One row per scheduled post in <strong>WordPress schedule order</strong> (same as create). Use <strong>Apply for</strong> so the sum matches post quantity.
                Drag row numbers to reorder links within a box. Saving updates keyword/URL/media/nofollow and queues remote updates for published posts.
            </p>

            @if ($postQuantity < 1)
                <p class="text-gray-500 !mt-4">No campaign articles found.</p>
            @else
                <form action="{{ route('admin.wp.schedule.campaign.multi.keywords.update', $campaign->id) }}" method="post"
                    class="w-full flex flex-col gap-3 !mt-4" id="wp-sched-multi-keyword-form">
                    @csrf
                    <input type="hidden" name="keywordmethod" value="multiple">
                    <input type="hidden" name="keywordsDataHolder" id="wpSchedKeywordsDataHolder" value="">

                    <div class="w-full flex flex-col gap-1 justify-between overflow-hidden overflow-y-auto max-h-[560px] border border-gray-200 rounded-lg !p-3 bg-gray-50"
                        id="wp-sched-multi-level-keyword-url-container">
                        <div class="w-full flex flex-col gap-1 max-h-[420px] overflow-hidden overflow-y-auto"
                            id="wp-sched-multi-key-url-box-parent"></div>

                        <div class="w-full flex flex-wrap justify-end !py-2">
                            <div class="w-full sm:w-1/2 flex justify-between items-center text-sm">
                                <p>Total <span id="wp-sched-multi-total-box-count">0</span></p>
                                <a href="javascript:void(0)" class="bg-yellow-400 !p-2 text-sm rounded text-white"
                                    id="wp-sched-add-more-multi-keyword-Url">+Add More</a>
                            </div>
                        </div>
                    </div>

                    <div class="w-full flex flex-wrap justify-between items-center gap-3 !mt-2 !pt-2 border-t border-gray-200">
                        <div class="flex gap-2 items-center">
                            <input type="checkbox" name="no_follow" id="wp-sched-no-follow" value="1"
                                {{ ! empty($initialNofollow) ? 'checked' : '' }}>
                            <label for="wp-sched-no-follow" class="text-sm">No Follow</label>
                            <span class="text-[12px] text-gray-500">(Check here to get Nofollow Link)</span>
                        </div>
                        <button type="submit" id="wp-sched-multi-keyword-submit"
                            class="!px-4 !py-3 rounded-lg bg-green-600 text-white hover:opacity-90 disabled:opacity-70 disabled:cursor-not-allowed">
                            Save &amp; sync to remote posts
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    @if ($postQuantity >= 1)
        <script>
            window.__editMultiKeywordConfig = {
                postCount: {{ (int) $postQuantity }},
                initial: { boxes: @json($multiLevelBoxes) },
                ids: {
                    wrap: 'wp-sched-multi-key-url-box-parent',
                    container: 'wp-sched-multi-level-keyword-url-container',
                    form: 'wp-sched-multi-keyword-form',
                    holder: 'wpSchedKeywordsDataHolder',
                    totalSpan: 'wp-sched-multi-total-box-count',
                    submitBtn: 'wp-sched-multi-keyword-submit',
                    addMore: 'wp-sched-add-more-multi-keyword-Url',
                    noFollow: 'wp-sched-no-follow',
                },
            };
        </script>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script src="{{ asset('js/edit-campaign-multi-keyword.js') }}"></script>
    @endif
    <script>
    (function() {
        var initialTab = @json($editTab);
        var tabBtns = document.querySelectorAll('.wp-sched-edit-tab');
        var panels = document.querySelectorAll('.wp-sched-edit-panel');

        function setWpSchedEditTab(tab) {
            tabBtns.forEach(function(btn) {
                var name = btn.getAttribute('data-wp-sched-edit-tab');
                var active = name === tab;
                btn.classList.toggle('text-[var(--primary-color)]', active);
                var dots = btn.querySelectorAll('.heading-dots');
                var line = btn.querySelector('.heading-line');
                dots.forEach(function(d) {
                    d.classList.toggle('bg-[var(--primary-color)]', active);
                    d.classList.toggle('bg-gray-300', !active);
                });
                if (line) {
                    line.classList.toggle('bg-[var(--primary-color)]', active);
                    line.classList.toggle('bg-gray-300', !active);
                }
            });
            panels.forEach(function(panel) {
                var name = panel.getAttribute('data-wp-sched-edit-panel');
                if (name === tab) {
                    panel.classList.remove('hidden');
                    setTimeout(function() {
                        panel.classList.remove('opacity-0', 'translate-y-5');
                    }, 20);
                } else {
                    panel.classList.add('hidden', 'opacity-0', 'translate-y-5');
                }
            });
        }

        tabBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                setWpSchedEditTab(btn.getAttribute('data-wp-sched-edit-tab'));
            });
        });
        setWpSchedEditTab(initialTab);

        var statusText = document.getElementById('campaign-status-text');
        var statusBox = document.getElementById('campaign-status-box');
        var normalStatus = "{{ ucfirst($campaign->status ?? '') }}";

        var bulkForm = document.getElementById('bulk-keyword-form');
        var submitBtn = document.getElementById('bulk-update-submit-btn');
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

        var multiForm = document.getElementById('wp-sched-multi-keyword-form');
        var multiSubmit = document.getElementById('wp-sched-multi-keyword-submit');
        if (multiForm && multiSubmit && statusText) {
            multiForm.addEventListener('submit', function() {
                statusText.textContent = 'Updating...';
                if (statusBox) {
                    statusBox.classList.add('!bg-amber-50', '!border-amber-300');
                    statusBox.classList.remove('bg-gray-50', 'border-gray-200');
                }
                multiSubmit.disabled = true;
                multiSubmit.textContent = 'Updating...';
            });
        }

        if (statusText && statusBox && document.querySelector('.bg-green-100')) {
            statusText.textContent = 'Campaign is updated';
            statusBox.classList.add('!bg-green-50', '!border-green-300');
            statusBox.classList.remove('bg-gray-50', 'border-gray-200', '!bg-amber-50', '!border-amber-300');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update batches (DB + remote)';
            }
            if (multiSubmit) {
                multiSubmit.disabled = false;
                multiSubmit.textContent = 'Save & sync to remote posts';
            }
            setTimeout(function() {
                statusText.textContent = normalStatus;
                statusBox.classList.remove('!bg-green-50', '!border-green-300');
                statusBox.classList.add('bg-gray-50', 'border-gray-200');
            }, 5000);
        } else if (statusText && statusBox && document.querySelector('.bg-red-100')) {
            statusText.textContent = normalStatus;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update batches (DB + remote)';
            }
            if (multiSubmit) {
                multiSubmit.disabled = false;
                multiSubmit.textContent = 'Save & sync to remote posts';
            }
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
