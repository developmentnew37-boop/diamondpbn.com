@extends('admin.layout.layout')

@section('title', 'Bulk Edit Hidden Links - Admin Panel')

@push('style')
    <style>
        .edit-kw-url-shell { max-width: 850px; }
        .keyword-url-box.edit-kw-url-card { align-items: stretch; }
        .keyword-url-box.edit-kw-url-card .box-count { z-index: 2; }
        .keyword-url-box.edit-kw-url-card .keywords-area-parent {
            display: flex; flex-wrap: nowrap; align-items: stretch; gap: 0.35rem; width: 100%;
        }
        .keyword-url-box.edit-kw-url-card .keywords-area-parent > div:first-child {
            width: 78%; flex: 0 0 78%; min-width: 0;
        }
        .keyword-url-box.edit-kw-url-card .keywords-area-parent > div:last-child {
            width: 20%; flex: 0 0 20%; min-width: 2.75rem;
        }
        .keyword-url-box.edit-kw-url-card textarea { box-sizing: border-box; min-height: 7.75rem; width: 100%; }
        @media (max-width: 1023px) {
            .keyword-url-box.edit-kw-url-card { flex-direction: column; }
            .keyword-url-box.edit-kw-url-card .kw-url-right-col {
                border-left: none; border-top: 1px solid rgba(234, 179, 99, 0.45);
            }
        }
    </style>
@endpush

@section('main-content')

    @php
        $rawTab = session('edit_hidden_link_campaign_tab', 'normal');
        if ($rawTab === 'batch') {
            $rawTab = 'bulk';
        }
        if ($rawTab === 'single') {
            $rawTab = 'normal';
        }
        $editTab = in_array($rawTab, ['campaign', 'normal', 'bulk'], true) ? $rawTab : 'normal';
    @endphp

    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Bulk Edit Hidden Links</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.hidden.link.campaign.index') }}" class="breadcrumb-link">PBN Hidden Links</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.hidden.link.campaign.show', $campaign->id) }}" class="breadcrumb-link">{{ $campaign->campaign_no }}</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Bulk edit links</span>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                <a href="{{ route('admin.hidden.link.campaign.show', $campaign->id) }}"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm">Back to campaign</a>
            </div>
        </div>
    </div>

    @if (session('cus__success') || session('cus__error'))
        <div class="w-full flex flex-col gap-2 !mt-2">
            @if (session('cus__success'))
                <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">{{ session('cus__success') }}</div>
            @endif
            @if (session('cus__error'))
                <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">{{ session('cus__error') }}</div>
            @endif
        </div>
    @endif

    <div class="w-full flex flex-wrap justify-between items-start content-card !mt-3 !p-4">
        <h2 class="text-xl w-full font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2 max-w-fit">
            Hidden links bulk edit — {{ $campaign->campaign_no }}
        </h2>
        <div class="w-full flex flex-wrap gap-5 !p-2 !pb-0">
            <button type="button" data-hl-edit-tab="campaign"
                class="hl-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'campaign' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
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
            <button type="button" data-hl-edit-tab="normal"
                class="hl-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'normal' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                <span>Add keyword &amp; Url</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'normal' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'normal' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'normal' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                    </div>
                    <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $editTab === 'normal' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                </div>
            </button>
            <button type="button" data-hl-edit-tab="bulk"
                class="hl-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'bulk' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                <span>Add bulk keyword &amp; Url</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'bulk' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'bulk' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'bulk' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                    </div>
                    <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $editTab === 'bulk' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                </div>
            </button>
        </div>

        <div id="hl-edit-panel-campaign" data-hl-edit-panel="campaign"
            class="hl-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'campaign' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
                Update campaign title
            </h3>
            <form action="{{ route('admin.hidden.link.campaign.update', $campaign->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="edit_kw_tab" value="campaign">
                <div class="flex flex-col gap-2 max-w-md">
                    <input type="text" name="campaign_no" value="{{ old('campaign_no', $campaign->campaign_no) }}"
                        class="w-full rounded !p-3 text-sm border border-gray-300 focus:border-[var(--primary-color)]">
                    <button type="submit" id="hl-campaign-submit"
                        class="!py-2 !px-4 rounded bg-[var(--primary-color)] text-white w-fit">Update title</button>
                </div>
            </form>
        </div>

        <div id="hl-edit-panel-normal" data-hl-edit-panel="normal"
            class="hl-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'normal' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <div class="mx-auto w-full border border-gray-300 rounded-lg bg-gray-50 shadow-md overflow-hidden">
                <div class="w-full flex flex-wrap justify-between items-center gap-2 !bg-gray-200 !px-3 !py-2 border-b border-gray-300">
                    <h3 class="text-base font-semibold text-gray-800">URL Keywords</h3>
                    <p class="text-sm text-gray-700">Groups <span class="font-semibold text-[var(--primary-color)]">({{ count($distinctBatches) }})</span></p>
                </div>
                <div class="!p-3">
                    <p class="text-sm text-gray-600 !mb-3">
                        Each row is one distinct <strong>keyword + URL</strong> batch. Editing a row updates that whole batch on remote sites.
                    </p>

            @if (empty($distinctBatches))
                <p class="text-gray-500">No links in this campaign.</p>
                <a href="{{ route('admin.hidden.link.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
            @else
                <form action="{{ route('admin.hidden.link.campaign.update', $campaign->id) }}" method="POST" class="w-full flex flex-col gap-3" id="hl-normal-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="edit_kw_tab" value="normal">

                    <div class="w-full flex flex-col gap-3 max-h-[min(520px,68vh)] overflow-y-auto overflow-x-hidden pr-0.5">
                        @foreach ($distinctBatches as $idx => $batch)
                            <div class="w-full border border-gray-200 rounded-lg !p-4 bg-white">
                                <input type="hidden" name="batch_representative_link_id[]" value="{{ $batch['representative_link_id'] }}">
                                <div class="flex items-center justify-between !mb-2">
                                    <span class="font-medium text-gray-700">Batch {{ $idx + 1 }}</span>
                                    <span class="text-sm text-gray-500">{{ $batch['count'] }} link(s){{ (($batch['on_remote'] ?? 0) > 0) ? ', ' . ($batch['on_remote'] ?? 0) . ' on remote' : '' }}</span>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-0.5">Keyword</label>
                                        <input type="text"
                                            name="batch_keyword[]"
                                            value="{{ old("batch_keyword.{$idx}", $batch['keyword']) }}"
                                            class="w-full border border-gray-300 rounded !px-2 !py-2 text-sm"
                                            maxlength="500"
                                            placeholder="Keyword">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-0.5">URL</label>
                                        <input type="url"
                                            name="batch_url[]"
                                            value="{{ old("batch_url.{$idx}", $batch['url']) }}"
                                            class="w-full border border-gray-300 rounded !px-2 !py-2 text-sm"
                                            maxlength="500"
                                            placeholder="https://...">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="w-full flex flex-wrap justify-end !py-1 add-more-window keyword-tab-sec">
                        <div class="w-full flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 text-sm text-gray-600">
                            <p>Total <span class="total-box-count font-semibold text-gray-800">{{ count($distinctBatches) }}</span></p>
                            <p class="text-xs sm:text-sm text-gray-500 max-w-md text-right">New rows are added when you add links in the campaign — not here.</p>
                        </div>
                    </div>

                    <div class="w-full flex flex-wrap justify-end items-center gap-3 !bg-gray-200 !px-3 !py-2 -mx-3 -mb-3 mt-1 border-t border-gray-300">
                        <button type="submit" id="hl-normal-submit"
                            class="cursor-pointer bg-green-500 hover:bg-green-600 text-white rounded !px-5 !py-3 text-sm font-medium disabled:opacity-70">
                            Save change
                        </button>
                    </div>
                </form>
            @endif
                </div>
            </div>
        </div>

        @php
            $bulkUrlsDefault = implode("\n", array_map(fn ($b) => $b['url'], $allLinksForBulk ?? []));
            $bulkKeywordsDefault = implode("\n", array_map(fn ($b) => $b['keyword'], $allLinksForBulk ?? []));
            $bulkCount = count($allLinksForBulk ?? []);
        @endphp
        <div id="hl-edit-panel-bulk" data-hl-edit-panel="bulk"
            class="hl-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'bulk' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
                Update keywords &amp; URLs (bulk textarea)
            </h3>
            <p class="text-sm text-gray-600 !mb-4">
                Enter one URL per line and one keyword per line. Both textareas must have exactly <strong>{{ $bulkCount }}</strong> non-empty lines (one per current link row).
            </p>

            @if (empty($allLinksForBulk))
                <p class="text-gray-500">No links in this campaign.</p>
                <a href="{{ route('admin.hidden.link.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
            @else
                <form action="{{ route('admin.hidden.link.campaign.update', $campaign->id) }}" method="POST" class="w-full" id="hl-bulk-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="edit_kw_tab" value="bulk">
                    @foreach ($allLinksForBulk as $linkRow)
                        <input type="hidden" name="batch_representative_link_id[]" value="{{ $linkRow['link_id'] }}">
                    @endforeach

                    <div class="w-full flex flex-wrap justify-between max-h-[420px] overflow-hidden overflow-y-auto bg-gray-100 rounded border border-gray-200 !p-3">
                        <div class="w-[49.5%] flex flex-col gap-2">
                            <div class="flex items-center">
                                <label for="hl-bulk-urls-edit"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Bulk URLs</label>
                                <span class="bulk-count text-sm flex !ml-[2px]" id="hl-bulk-urls-edit-count"></span>
                            </div>
                            <textarea name="bulk_urls" id="hl-bulk-urls-edit"
                                class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none"
                                rows="14">{{ old('bulk_urls', $bulkUrlsDefault) }}</textarea>
                        </div>
                        <div class="w-[49.5%] flex flex-col gap-2">
                            <div class="flex items-center">
                                <label for="hl-bulk-keywords-edit"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Bulk Keywords</label>
                                <span class="bulk-count text-sm flex !ml-[2px]" id="hl-bulk-keywords-edit-count"></span>
                            </div>
                            <textarea name="bulk_keywords" id="hl-bulk-keywords-edit"
                                class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none"
                                rows="14">{{ old('bulk_keywords', $bulkKeywordsDefault) }}</textarea>
                        </div>
                    </div>
                    <div class="!mt-2 text-xs text-gray-500">Required non-empty lines: {{ $bulkCount }} in each textarea.</div>
                    <div class="!mt-4 flex gap-2">
                        <button type="submit" id="hl-bulk-submit" class="!px-4 !py-2 bg-[var(--primary-color)] text-white rounded hover:opacity-90">Update bulk data &amp; sync to remote</button>
                        <a href="{{ route('admin.hidden.link.campaign.show', $campaign->id) }}" class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
    (function() {
        var initialTab = @json($editTab);
        var tabBtns = document.querySelectorAll('.hl-edit-tab');
        var panels = document.querySelectorAll('.hl-edit-panel');
        function setTab(tab) {
            tabBtns.forEach(function(btn) {
                var name = btn.getAttribute('data-hl-edit-tab');
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
                var name = panel.getAttribute('data-hl-edit-panel');
                if (name === tab) {
                    panel.classList.remove('hidden');
                    setTimeout(function() { panel.classList.remove('opacity-0', 'translate-y-5'); }, 20);
                } else {
                    panel.classList.add('hidden', 'opacity-0', 'translate-y-5');
                }
            });
        }
        tabBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                setTab(btn.getAttribute('data-hl-edit-tab'));
            });
        });
        setTab(initialTab);

        var nf = document.getElementById('hl-normal-form');
        var ns = document.getElementById('hl-normal-submit');
        if (nf && ns) {
            nf.addEventListener('submit', function() {
                ns.disabled = true;
                ns.textContent = 'Saving...';
            });
        }
        var bf = document.getElementById('hl-bulk-form');
        var bs = document.getElementById('hl-bulk-submit');
        if (bf && bs) {
            var bulkUrlsTa = document.getElementById('hl-bulk-urls-edit');
            var bulkKeywordsTa = document.getElementById('hl-bulk-keywords-edit');
            function nonEmptyCount(val) {
                return (val || '').split(/\r\n|\r|\n/).filter(function(line) { return line.trim() !== ''; }).length;
            }
            function refreshBulkCounts() {
                var cu = document.getElementById('hl-bulk-urls-edit-count');
                var ck = document.getElementById('hl-bulk-keywords-edit-count');
                if (cu && bulkUrlsTa) cu.textContent = '(' + nonEmptyCount(bulkUrlsTa.value) + ' lines)';
                if (ck && bulkKeywordsTa) ck.textContent = '(' + nonEmptyCount(bulkKeywordsTa.value) + ' lines)';
            }
            if (bulkUrlsTa) bulkUrlsTa.addEventListener('input', refreshBulkCounts);
            if (bulkKeywordsTa) bulkKeywordsTa.addEventListener('input', refreshBulkCounts);
            refreshBulkCounts();
            bf.addEventListener('submit', function(e) {
                var expected = {{ $bulkCount }};
                var uCount = bulkUrlsTa ? nonEmptyCount(bulkUrlsTa.value) : 0;
                var kCount = bulkKeywordsTa ? nonEmptyCount(bulkKeywordsTa.value) : 0;
                if (uCount !== expected || kCount !== expected) {
                    e.preventDefault();
                    alert('Bulk URLs and Bulk Keywords must each have exactly ' + expected + ' non-empty lines.');
                    return;
                }
                bs.disabled = true;
                bs.textContent = 'Updating...';
            });
        }
        var cf = document.querySelector('#hl-edit-panel-campaign form');
        var cs = document.getElementById('hl-campaign-submit');
        if (cf && cs) {
            cf.addEventListener('submit', function() {
                cs.disabled = true;
                cs.textContent = 'Saving...';
            });
        }
        if (document.querySelector('.bg-green-100') || document.querySelector('.bg-red-100')) {
            if (ns) { ns.disabled = false; ns.textContent = 'Save change'; }
            if (bs) { bs.disabled = false; bs.textContent = 'Update bulk data & sync to remote'; }
            if (cs) { cs.disabled = false; cs.textContent = 'Update title'; }
        }
    })();
    </script>
    @endpush

@endsection
