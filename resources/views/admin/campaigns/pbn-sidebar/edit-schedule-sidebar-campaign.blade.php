@extends('admin.layout.layout')

@section('title', 'Edit Schedule Sidebar Campaign')

@push('style')
    <style>
        .edit-kw-url-shell { width: 100%; max-width: none; }
        .keyword-url-box.edit-kw-url-card { align-items: stretch; width: 100%; min-width: 0; }
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
        $rawTab = session('edit_schedule_sidebar_campaign_tab', 'normal');
        if ($rawTab === 'batch') {
            $rawTab = 'bulk';
        }
        if ($rawTab === 'single') {
            $rawTab = 'normal';
        }
        $editTab = in_array($rawTab, ['campaign', 'normal', 'bulk', 'rawanchor'], true) ? $rawTab : 'normal';
    @endphp

    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Edit Schedule Sidebar Campaign</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.schedule.sidebar.campaign.index') }}" class="breadcrumb-link">Schedule Blogroll</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}" class="breadcrumb-link">{{ $campaign->campaign_no }}</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Edit</span>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center gap-2">
                <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm">View campaign</a>
                <a href="javascript:void(0)" onclick="history.back()"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm">Back</a>
            </div>
        </div>
    </div>

    @if (session('cus__success') || session('cus__error'))
        <div class="w-full flex flex-col gap-2 !mt-2">
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

    <div class="w-full flex flex-wrap justify-between items-start content-card !p-4 !mt-2">
        <h2 class="text-xl w-full font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2 max-w-fit">
            Schedule blogroll — {{ $campaign->campaign_no }}
        </h2>
        <div class="w-full flex flex-wrap gap-5 !p-2 !pb-0">
            <button type="button" data-ss-edit-tab="campaign"
                class="ss-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'campaign' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
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
            <button type="button" data-ss-edit-tab="normal"
                class="ss-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'normal' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
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
            <button type="button" data-ss-edit-tab="bulk"
                class="ss-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'bulk' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
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
            <button type="button" data-ss-edit-tab="rawanchor"
                class="ss-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'rawanchor' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                <span>Raw HTML Anchors</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'rawanchor' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'rawanchor' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'rawanchor' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                    </div>
                    <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $editTab === 'rawanchor' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                </div>
            </button>
        </div>

        <div id="ss-edit-panel-campaign" data-ss-edit-panel="campaign"
            class="ss-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'campaign' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
                Update campaign number
            </h3>
            <form action="{{ route('admin.schedule.sidebar.campaign.update', $campaign->id) }}" method="post">
                @csrf
                @method('PUT')
                <div class="flex flex-col gap-2 max-w-md">
                    <input type="text" name="campaign_no" value="{{ old('campaign_no', $campaign->campaign_no) }}"
                        class="w-full rounded !p-3 text-sm border border-gray-300 focus:border-[var(--primary-color)]">
                    @error('campaign_no')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <button type="submit" id="ss-campaign-submit" class="!py-2 !px-4 rounded bg-[var(--primary-color)] text-white w-fit">Update campaign no</button>
                </div>
            </form>
        </div>

        <div id="ss-edit-panel-normal" data-ss-edit-panel="normal"
            class="ss-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'normal' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <div class="edit-kw-url-shell w-full border border-gray-300 rounded-lg bg-gray-50 shadow-md overflow-hidden">
                <div class="w-full flex flex-wrap justify-between items-center gap-2 !bg-gray-200 !px-3 !py-2 border-b border-gray-300">
                    <h3 class="text-base font-semibold text-gray-800">URL Keywords</h3>
                    <p class="text-sm text-gray-700">Link rows <span class="font-semibold text-[var(--primary-color)]">({{ count($allLinksForBulk ?? []) }})</span></p>
                </div>
                <div class="!p-3">
                    <p class="text-sm text-gray-600 !mb-3">
                        Same layout as creating a blogroll campaign: each box is a target URL, a row count (how many scheduled link rows use that URL), and keywords with per-line quantities. The sum of row counts must equal the link row count. When you open this page, consecutive identical keywords under each URL are shown once with a combined count in the quantity column. Saving updates each row in order and queues remote sync where applicable.
                    </p>

            @if (empty($allLinksForBulk))
                <p class="text-gray-500">No links to edit in this campaign.</p>
                <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
            @else
                <form action="{{ route('admin.schedule.sidebar.campaign.bulk.update', $campaign->id) }}" method="POST" class="w-full flex flex-col gap-3" id="ss-normal-form">
                    @csrf
                    <input type="hidden" name="edit_kw_tab" value="normal">
                    <div id="ss-dynamic-batch-fields"></div>

                    <div class="w-full flex flex-col gap-0 max-h-[min(560px,72vh)] overflow-y-auto overflow-x-hidden border border-orange-200/60 rounded-lg bg-orange-50/40">
                        <div class="w-full flex flex-col gap-3 !p-3" id="ss-keyword-url-container"></div>
                    </div>

                    <div class="w-full flex flex-wrap justify-between items-center gap-2 !py-2 text-sm text-gray-700">
                        <div class="flex items-center gap-2">
                            <span>Boxes:</span>
                            <span class="font-semibold text-gray-900"><span id="ss-total-box-count">0</span></span>
                        </div>
                        <a href="javascript:void(0)" id="ss-add-more-keyword-url"
                            class="inline-flex items-center bg-blue-500 hover:bg-blue-600 !px-3 !py-2 text-sm rounded text-white">+ Add more URL</a>
                    </div>

                    <div class="w-full flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 text-sm text-gray-600 !py-1">
                        <p>Total link rows <span class="font-semibold text-gray-800">{{ count($allLinksForBulk ?? []) }}</span> (fixed)</p>
                        <p class="text-xs sm:text-sm text-gray-500 max-w-md text-right">Need one line per keyword? Use the bulk textarea tab.</p>
                    </div>

                    <div class="w-full flex flex-wrap justify-end items-center gap-3 !bg-gray-200 !px-3 !py-2 -mx-3 -mb-3 mt-1 border-t border-gray-300">
                        <button type="submit" id="ss-normal-submit"
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
        <div id="ss-edit-panel-bulk" data-ss-edit-panel="bulk"
            class="ss-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'bulk' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
                Update keywords and URLs (bulk textarea)
            </h3>
            <p class="text-sm text-gray-600 !mb-4">
                Enter one URL per line and one keyword per line. Both textareas must have exactly <strong>{{ $bulkCount }}</strong> non-empty lines (one per current link row).
            </p>

            @if (empty($allLinksForBulk))
                <p class="text-gray-500">No published links to edit.</p>
                <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
            @else
                <form action="{{ route('admin.schedule.sidebar.campaign.bulk.update', $campaign->id) }}" method="POST" class="w-full" id="ss-bulk-form">
                    @csrf
                    <input type="hidden" name="edit_kw_tab" value="bulk">
                    @foreach ($allLinksForBulk as $linkRow)
                        <input type="hidden" name="batch_representative_link_id[]" value="{{ $linkRow['link_id'] }}">
                    @endforeach

                    <div class="w-full flex flex-wrap justify-between max-h-[420px] overflow-hidden overflow-y-auto bg-gray-100 rounded border border-gray-200 !p-3">
                        <div class="w-[49.5%] flex flex-col gap-2">
                            <div class="flex items-center">
                                <label for="ss-bulk-urls-edit"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Bulk URLs</label>
                                <span class="bulk-count text-sm flex !ml-[2px]" id="ss-bulk-urls-edit-count"></span>
                            </div>
                            <textarea name="bulk_urls" id="ss-bulk-urls-edit"
                                class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none"
                                rows="14">{{ old('bulk_urls', $bulkUrlsDefault) }}</textarea>
                        </div>
                        <div class="w-[49.5%] flex flex-col gap-2">
                            <div class="flex items-center">
                                <label for="ss-bulk-keywords-edit"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Bulk Keywords</label>
                                <span class="bulk-count text-sm flex !ml-[2px]" id="ss-bulk-keywords-edit-count"></span>
                            </div>
                            <textarea name="bulk_keywords" id="ss-bulk-keywords-edit"
                                class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none"
                                rows="14">{{ old('bulk_keywords', $bulkKeywordsDefault) }}</textarea>
                        </div>
                    </div>
                    <div class="!mt-2 text-xs text-gray-500">Required non-empty lines: {{ $bulkCount }} in each textarea.</div>
                    <div class="!mt-4 flex gap-2">
                        <button type="submit" id="ss-bulk-submit" class="!px-4 !py-2 bg-[var(--primary-color)] text-white rounded hover:opacity-90">
                            Update bulk data and sync to remote
                        </button>
                        <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}"
                            class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</a>
                    </div>
                </form>
            @endif
        </div>

        {{-- Raw HTML Anchors Panel --}}
        <div id="ss-edit-panel-rawanchor" data-ss-edit-panel="rawanchor"
            class="ss-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'rawanchor' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
                Update with Raw HTML Anchors
            </h3>
            <p class="text-sm text-gray-600 !mb-4">
                Paste raw HTML anchor tags. The system will extract URLs, keywords, and rel attributes automatically.
            </p>

            @if (empty($allLinksForBulk))
                <p class="text-gray-500">No published links to edit.</p>
                <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
            @else
                <form action="{{ route('admin.schedule.sidebar.campaign.bulk.update', $campaign->id) }}" method="POST" class="w-full" id="ss-rawanchor-form">
                    @csrf
                    <input type="hidden" name="edit_kw_tab" value="rawanchor">
                    @foreach ($allLinksForBulk as $linkRow)
                        <input type="hidden" name="batch_representative_link_id[]" value="{{ $linkRow['link_id'] }}">
                    @endforeach

                    <div class="w-full flex flex-col gap-3">
                        <div class="w-full bg-blue-50 border border-blue-200 rounded !p-3">
                            <h4 class="text-sm font-semibold text-blue-800 !mb-2">Instructions:</h4>
                            <ul class="text-xs text-blue-700 list-disc !pl-5 space-y-1">
                                <li>Paste anchor tags directly (e.g., <code>&lt;a href="url" rel="nofollow sponsored"&gt;keyword&lt;/a&gt;</code>)</li>
                                <li>One line per sidebar link, or separate multiple anchors per line with commas (max 5 per line)</li>
                                <li>Total lines must equal link count: <strong>{{ $bulkCount }}</strong></li>
                                <li>System will automatically extract URLs, keywords, and all rel attributes</li>
                                <li>Supports all rel attributes (nofollow, sponsored, ugc, noopener, noreferrer, etc.)</li>
                            </ul>
                        </div>

                        <div class="w-full flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <label for="ss-raw-html-anchors-edit" class="text-sm font-medium">Raw HTML Anchors</label>
                                <span class="text-xs text-gray-500" id="ss-raw-html-anchors-edit-count">(0 lines)</span>
                            </div>
                            <textarea name="raw_html_anchors" id="ss-raw-html-anchors-edit"
                                class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full resize-none font-mono"
                                rows="15"
                                placeholder='<a href="https://example.com" rel="nofollow">keyword</a>&#10;<a href="https://example2.com" rel="sponsored ugc">keyword2</a>, <a href="https://example3.com">keyword3</a>&#10;...'></textarea>
                        </div>
                    </div>

                    <div class="!mt-2 text-xs text-gray-500">
                        Required lines: {{ $bulkCount }}
                    </div>

                    <div class="!mt-4 flex gap-2">
                        <button type="submit" id="ss-rawanchor-submit" class="!px-4 !py-2 bg-[var(--primary-color)] text-white rounded hover:opacity-90">
                            Update from raw anchors &amp; sync to remote
                        </button>
                        <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}"
                            class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    @if (!empty($allLinksForBulk))
        <script>window.__ssEditLinksForNormal = @json($allLinksForBulk);</script>
        <script src="{{ asset('js/edit-schedule-sidebar-normal-tab.js') }}" defer></script>
    @endif
    <script>
    (function() {
        var initialTab = @json($editTab);
        var tabBtns = document.querySelectorAll('.ss-edit-tab');
        var panels = document.querySelectorAll('.ss-edit-panel');
        function setTab(tab) {
            tabBtns.forEach(function(btn) {
                var name = btn.getAttribute('data-ss-edit-tab');
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
                var name = panel.getAttribute('data-ss-edit-panel');
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
                setTab(btn.getAttribute('data-ss-edit-tab'));
            });
        });
        setTab(initialTab);

        var ns = document.getElementById('ss-normal-submit');
        var bf = document.getElementById('ss-bulk-form');
        var bs = document.getElementById('ss-bulk-submit');
        if (bf && bs) {
            var bulkUrlsTa = document.getElementById('ss-bulk-urls-edit');
            var bulkKeywordsTa = document.getElementById('ss-bulk-keywords-edit');
            function nonEmptyCount(val) {
                return (val || '').split(/\r\n|\r|\n/).filter(function(line) { return line.trim() !== ''; }).length;
            }
            function refreshBulkCounts() {
                var cu = document.getElementById('ss-bulk-urls-edit-count');
                var ck = document.getElementById('ss-bulk-keywords-edit-count');
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
        var cf = document.querySelector('#ss-edit-panel-campaign form');
        var cs = document.getElementById('ss-campaign-submit');
        if (cf && cs) {
            cf.addEventListener('submit', function() {
                cs.disabled = true;
                cs.textContent = 'Saving...';
            });
        }
        if (document.querySelector('.bg-green-100') || document.querySelector('.bg-red-100')) {
            if (bs) { bs.disabled = false; bs.textContent = 'Update bulk data and sync to remote'; }
            if (ns) { ns.disabled = false; ns.textContent = 'Save change'; }
            if (cs) { cs.disabled = false; cs.textContent = 'Update campaign no'; }
        }

        // Raw HTML Anchors form handling
        var raf = document.getElementById('ss-rawanchor-form');
        var ras = document.getElementById('ss-rawanchor-submit');
        if (raf && ras) {
            var rawTa = document.getElementById('ss-raw-html-anchors-edit');
            function nonEmptyCount(val) {
                return (val || '').split(/\r\n|\r|\n/).filter(function(line) { return line.trim() !== ''; }).length;
            }
            function refreshRawCount() {
                var rc = document.getElementById('ss-raw-html-anchors-edit-count');
                if (rc && rawTa) rc.textContent = '(' + nonEmptyCount(rawTa.value) + ' lines)';
            }
            if (rawTa) rawTa.addEventListener('input', refreshRawCount);
            refreshRawCount();
            raf.addEventListener('submit', function(e) {
                var expected = {{ $bulkCount }};
                var count = rawTa ? nonEmptyCount(rawTa.value) : 0;
                if (count !== expected) {
                    e.preventDefault();
                    alert('Raw HTML Anchors must have exactly ' + expected + ' non-empty lines (found ' + count + ').');
                    return;
                }
                ras.disabled = true;
                ras.textContent = 'Updating...';
            });
        }

        // Reset button states on alert display
        if (document.querySelector('.bg-green-100') || document.querySelector('.bg-red-100')) {
            if (mbs) { mbs.disabled = false; mbs.textContent = 'Update multi bulk data & sync to remote'; }
            if (ras) { ras.disabled = false; ras.textContent = 'Update from raw anchors & sync to remote'; }
        }
    })();
    </script>
    @endpush

@endsection
