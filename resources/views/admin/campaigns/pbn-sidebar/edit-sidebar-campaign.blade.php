@extends('admin.layout.layout')

@section('title', 'Bulk Edit Sidebar Links - Admin Panel')

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
        .keyword-url-box.edit-kw-url-card .keywords-area,
        .keyword-url-box.edit-kw-url-card .keywords-quantity-area {
            min-height: 7.75rem; box-sizing: border-box; width: 100%;
        }
        @media (max-width: 1023px) {
            .keyword-url-box.edit-kw-url-card { flex-direction: column; }
            .keyword-url-box.edit-kw-url-card .kw-url-right-col {
                border-left: none;
                border-top: 1px solid rgba(234, 179, 99, 0.45);
            }
        }
    </style>
@endpush

@section('main-content')

    @php
        $rawTab = session('edit_sidebar_campaign_tab', 'normal');
        if ($rawTab === 'batch') {
            $rawTab = 'bulk';
        }
        if ($rawTab === 'single') {
            $rawTab = 'normal';
        }
        $editTab = in_array($rawTab, ['campaign', 'normal', 'bulk', 'rawanchor'], true) ? $rawTab : 'normal';
    @endphp

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3 min-w-0">
                <h2 class="page-title !mb-0 min-w-0 shrink leading-tight">Bulk Edit Sidebar Links</h2>
                <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}"
                    class="inline-flex items-center justify-center gap-2 shrink-0 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm whitespace-nowrap"
                    aria-label="Back to campaign view">Back to campaign</a>
            </div>
            <nav class="flex flex-wrap items-baseline gap-x-1.5 gap-y-2 text-sm text-gray-600 w-full min-w-0 leading-snug"
                aria-label="Breadcrumb">
                <span class="inline-flex flex-wrap items-baseline gap-x-1.5 min-w-0">
                    <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link shrink-0">Dashboard</a>
                    <span class="text-gray-400 shrink-0" aria-hidden="true">›</span>
                </span>
                <span class="inline-flex flex-wrap items-baseline gap-x-1.5 min-w-0">
                    <a href="{{ route('admin.sidebar.campaign.index') }}" class="breadcrumb-link">PBN Blogroll</a>
                    <span class="text-gray-400 shrink-0" aria-hidden="true">›</span>
                </span>
                <span class="inline-flex flex-wrap items-baseline gap-x-1.5 min-w-0 max-w-full">
                    <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}"
                        class="breadcrumb-link break-all font-mono text-xs sm:text-sm"
                        title="{{ $campaign->campaign_no }}">{{ $campaign->campaign_no }}</a>
                    <span class="text-gray-400 shrink-0" aria-hidden="true">›</span>
                </span>
                <span class="text-gray-600 min-w-0">Bulk edit links</span>
            </nav>
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

    <div class="w-full flex flex-wrap justify-between items-start content-card !mt-3 !p-4">
        <h2 class="text-xl w-full font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2 max-w-fit">
            Blogroll bulk edit — {{ $campaign->campaign_no }}
        </h2>
        <div class="w-full flex flex-wrap gap-5 !p-2 !pb-0">
            <button type="button" data-sb-edit-tab="campaign"
                class="sb-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'campaign' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
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
            <button type="button" data-sb-edit-tab="normal"
                class="sb-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'normal' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
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
            <button type="button" data-sb-edit-tab="bulk"
                class="sb-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'bulk' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
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
            <button type="button" data-sb-edit-tab="rawanchor"
                class="sb-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'rawanchor' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
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

        <div id="sb-edit-panel-campaign" data-sb-edit-panel="campaign"
            class="sb-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'campaign' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
                Update campaign title
            </h3>
            <form action="{{ route('admin.sidebar.campaign.update', $campaign->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="edit_kw_tab" value="campaign">
                <div class="flex flex-col gap-2 max-w-md">
                    <input type="text" name="campaign_no" value="{{ old('campaign_no', $campaign->campaign_no) }}"
                        class="w-full rounded !p-3 text-sm border border-gray-300 focus:border-[var(--primary-color)]">
                    <button type="submit" id="sb-campaign-submit"
                        class="!py-2 !px-4 rounded bg-[var(--primary-color)] text-white w-fit">Update title</button>
                </div>
            </form>
        </div>

        <div id="sb-edit-panel-normal" data-sb-edit-panel="normal"
            class="sb-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'normal' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <div class="mx-auto w-full border border-gray-300 rounded-lg bg-gray-50 shadow-md overflow-hidden">
                <div class="w-full flex flex-wrap justify-between items-center gap-2 !bg-gray-200 !px-3 !py-2 border-b border-gray-300">
                    <h3 class="text-base font-semibold text-gray-800">URL Keywords</h3>
                    <p class="text-sm text-gray-700">Published groups <span class="font-semibold text-[var(--primary-color)]">({{ count($distinctBatches) }})</span></p>
                </div>
                <div class="!p-3">
                    <p class="text-sm text-gray-600 !mb-3">
                        Each row is one distinct <strong>keyword + URL</strong> batch shared on remote. Changing a row updates that whole batch on remote sites.
                    </p>

            @if (empty($distinctBatches))
                <p class="text-gray-500">No published links to edit. Only successfully published tasks (on remote) appear here.</p>
                <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
            @else
                <form action="{{ route('admin.sidebar.campaign.update', $campaign->id) }}" method="POST" class="w-full flex flex-col gap-3" id="sb-normal-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="edit_kw_tab" value="normal">

                    <div class="w-full flex flex-col gap-3 max-h-[min(520px,68vh)] overflow-y-auto overflow-x-hidden pr-0.5">
                        @foreach ($distinctBatches as $idx => $batch)
                            <div class="w-full border border-gray-200 rounded-lg !p-4 bg-white">
                                <input type="hidden" name="batch_representative_link_id[]" value="{{ $batch['representative_link_id'] }}">
                                <div class="flex items-center justify-between !mb-2">
                                    <span class="font-medium text-gray-700">
                                        Batch {{ $idx + 1 }}
                                        @if ($batch['is_multiple'] ?? false)
                                            <span class="text-xs bg-blue-100 text-blue-700 !px-2 !py-0.5 rounded ml-2">Multiple Links ({{ $batch['pair_count'] }} pairs)</span>
                                        @endif
                                    </span>
                                    <span class="text-sm text-gray-500">{{ $batch['count'] }} link(s) on remote</span>
                                </div>

                                @if ($batch['is_multiple'] ?? false)
                                    {{-- Raw Anchor: Show all keyword/URL pairs grouped --}}
                                    <div class="bg-gray-50 border border-gray-200 rounded !p-3 !mb-2">
                                        <p class="text-xs text-gray-600 !mb-2">This website has {{ $batch['pair_count'] }} keyword/URL pairs:</p>
                                        <div class="flex flex-col gap-2">
                                            @foreach ($batch['keyword'] as $pairIdx => $keyword)
                                                <div class="flex items-start gap-2 text-sm">
                                                    <span class="text-gray-500 shrink-0">{{ $pairIdx + 1 }}.</span>
                                                    <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-2">
                                                        <div class="text-gray-700">
                                                            <span class="text-xs text-gray-500">Keyword:</span>
                                                            <span class="font-mono">{{ $keyword }}</span>
                                                        </div>
                                                        <div class="text-gray-700">
                                                            <span class="text-xs text-gray-500">URL:</span>
                                                            <span class="font-mono break-all">{{ $batch['url'][$pairIdx] ?? $batch['url'][0] ?? '' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <p class="text-xs text-amber-600 !mb-3">⚠️ Campaigns with multiple links per website cannot be edited individually. Use bulk textarea mode to update all links at once.</p>
                                @else
                                    {{-- Single: Show editable inputs --}}
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-0.5">Keyword</label>
                                            <input type="text"
                                                name="batch_keyword[]"
                                                value="{{ old("batch_keyword.{$idx}", is_array($batch['keyword']) ? $batch['keyword'][0] : $batch['keyword']) }}"
                                                class="w-full border border-gray-300 rounded !px-2 !py-2 text-sm"
                                                maxlength="500"
                                                placeholder="Keyword">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-0.5">URL</label>
                                            <input type="url"
                                                name="batch_url[]"
                                                value="{{ old("batch_url.{$idx}", is_array($batch['url']) ? $batch['url'][0] : $batch['url']) }}"
                                                class="w-full border border-gray-300 rounded !px-2 !py-2 text-sm"
                                                maxlength="500"
                                                placeholder="https://...">
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="w-full flex flex-wrap justify-end !py-1 add-more-window keyword-tab-sec">
                        <div class="w-full flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 text-sm text-gray-600">
                            <p>Total <span class="total-box-count font-semibold text-gray-800">{{ count($distinctBatches) }}</span></p>
                            <p class="text-xs sm:text-sm text-gray-500 max-w-md text-right">New URL rows are added when you publish more link variants from the campaign — not from this screen.</p>
                        </div>
                    </div>

                    <div class="w-full flex flex-wrap justify-end items-center gap-3 !bg-gray-200 !px-3 !py-2 -mx-3 -mb-3 mt-1 border-t border-gray-300">
                        <button type="submit" id="sb-normal-submit"
                            class="cursor-pointer bg-green-500 hover:bg-green-600 text-white rounded !px-5 !py-3 text-sm font-medium disabled:opacity-70 disabled:cursor-not-allowed">
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
        <div id="sb-edit-panel-bulk" data-sb-edit-panel="bulk"
            class="sb-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'bulk' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
                Update keywords &amp; URLs (bulk textarea)
            </h3>
            <p class="text-sm text-gray-600 !mb-4">
                Enter one URL per line and one keyword per line. Both textareas must have exactly <strong>{{ $bulkCount }}</strong> non-empty lines (one per current link row).
            </p>

            @if (empty($allLinksForBulk))
                <p class="text-gray-500">No published links to edit.</p>
                <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
            @else
                <form action="{{ route('admin.sidebar.campaign.update', $campaign->id) }}" method="POST" class="w-full" id="sb-bulk-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="edit_kw_tab" value="bulk">
                    @foreach ($allLinksForBulk as $linkRow)
                        <input type="hidden" name="batch_representative_link_id[]" value="{{ $linkRow['link_id'] }}">
                    @endforeach
                    <div
                        class="w-full flex flex-col gap-3 md:grid md:grid-cols-2 md:gap-3 max-h-none md:max-h-[420px] overflow-visible md:overflow-y-auto bg-gray-100 rounded border border-gray-200 !p-3 min-w-0">
                        <div class="w-full flex flex-col gap-2 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <label for="bulk-urls-edit"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                                    Bulk URLs
                                </label>
                                <span class="bulk-count text-xs sm:text-sm flex shrink-0" id="bulk-urls-edit-count"></span>
                            </div>
                            <textarea name="bulk_urls" id="bulk-urls-edit"
                                class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full min-w-0 resize-none bulk-edit-input"
                                rows="14">{{ old('bulk_urls', $bulkUrlsDefault) }}</textarea>
                        </div>
                        <div class="w-full flex flex-col gap-2 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <label for="bulk-keywords-edit"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                                    Bulk Keywords
                                </label>
                                <span class="bulk-count text-xs sm:text-sm flex shrink-0" id="bulk-keywords-edit-count"></span>
                            </div>
                            <textarea name="bulk_keywords" id="bulk-keywords-edit"
                                class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full min-w-0 resize-none bulk-edit-input"
                                rows="14">{{ old('bulk_keywords', $bulkKeywordsDefault) }}</textarea>
                        </div>
                    </div>
                    <div class="!mt-2 text-xs text-gray-500">
                        Required non-empty lines: {{ $bulkCount }} in each textarea.
                    </div>
                    <div class="!mt-4 flex flex-col sm:flex-row gap-2 sm:items-center">
                        <button type="submit" id="sb-bulk-submit" class="!px-4 !py-2 bg-[var(--primary-color)] text-white rounded hover:opacity-90">
                            Update bulk data &amp; sync to remote
                        </button>
                        <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}"
                            class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-center">Cancel</a>
                    </div>
                </form>
            @endif
        </div>

        {{-- Raw HTML Anchors Panel --}}
        <div id="sb-edit-panel-rawanchor" data-sb-edit-panel="rawanchor"
            class="sb-edit-panel w-full !p-2 duration-500 transition-all {{ $editTab === 'rawanchor' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
                Update with Raw HTML Anchors
            </h3>
            <p class="text-sm text-gray-600 !mb-4">
                Paste raw HTML anchor tags. The system will extract URLs, keywords, and rel attributes automatically.
            </p>

            @if (empty($allLinksForBulk))
                <p class="text-gray-500">No published links to edit.</p>
                <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
            @else
                <form action="{{ route('admin.sidebar.campaign.update', $campaign->id) }}" method="POST" class="w-full" id="sb-rawanchor-form">
                    @csrf
                    @method('PUT')
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
                                <label for="raw-html-anchors-edit" class="text-sm font-medium">Raw HTML Anchors</label>
                                <span class="text-xs text-gray-500" id="raw-html-anchors-edit-count">(0 lines)</span>
                            </div>
                            <textarea name="raw_html_anchors" id="raw-html-anchors-edit"
                                class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full resize-none font-mono"
                                rows="15"
                                placeholder='<a href="https://example.com" rel="nofollow">keyword</a>&#10;<a href="https://example2.com" rel="sponsored ugc">keyword2</a>, <a href="https://example3.com">keyword3</a>&#10;...'></textarea>
                        </div>
                    </div>

                    <div class="!mt-2 text-xs text-gray-500">
                        Required lines: {{ $bulkCount }}
                    </div>

                    <div class="!mt-4 flex flex-col sm:flex-row gap-2 sm:items-center">
                        <button type="submit" id="sb-rawanchor-submit" class="!px-4 !py-2 bg-[var(--primary-color)] text-white rounded hover:opacity-90">
                            Update from raw anchors &amp; sync to remote
                        </button>
                        <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}"
                            class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-center">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
    (function() {
        var initialTab = @json($editTab);
        var tabBtns = document.querySelectorAll('.sb-edit-tab');
        var panels = document.querySelectorAll('.sb-edit-panel');
        function setTab(tab) {
            tabBtns.forEach(function(btn) {
                var name = btn.getAttribute('data-sb-edit-tab');
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
                var name = panel.getAttribute('data-sb-edit-panel');
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
                setTab(btn.getAttribute('data-sb-edit-tab'));
            });
        });
        setTab(initialTab);

        var nf = document.getElementById('sb-normal-form');
        var ns = document.getElementById('sb-normal-submit');
        if (nf && ns) {
            nf.addEventListener('submit', function() {
                ns.disabled = true;
                ns.textContent = 'Saving...';
            });
        }
        var bf = document.getElementById('sb-bulk-form');
        var bs = document.getElementById('sb-bulk-submit');
        if (bf && bs) {
            var bulkUrlsTa = document.getElementById('bulk-urls-edit');
            var bulkKeywordsTa = document.getElementById('bulk-keywords-edit');
            function nonEmptyCount(val) {
                return (val || '').split(/\r\n|\r|\n/).filter(function(line) { return line.trim() !== ''; }).length;
            }
            function refreshBulkCounts() {
                var cu = document.getElementById('bulk-urls-edit-count');
                var ck = document.getElementById('bulk-keywords-edit-count');
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
        var cf = document.querySelector('#sb-edit-panel-campaign form');
        var cs = document.getElementById('sb-campaign-submit');
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

        // Raw HTML Anchors form handling
        var raf = document.getElementById('sb-rawanchor-form');
        var ras = document.getElementById('sb-rawanchor-submit');
        if (raf && ras) {
            var rawTa = document.getElementById('raw-html-anchors-edit');
            function nonEmptyCount(val) {
                return (val || '').split(/\r\n|\r|\n/).filter(function(line) { return line.trim() !== ''; }).length;
            }
            function refreshRawCount() {
                var rc = document.getElementById('raw-html-anchors-edit-count');
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
