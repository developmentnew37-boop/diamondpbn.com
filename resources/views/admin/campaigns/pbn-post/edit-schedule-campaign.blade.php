@extends('admin.layout.layout')

@section('title', ($campaign->is_sticky_campaign ?? false) ? 'Edit Sticky Schedule Campaign' : 'Edit Schedule Campaign')

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
                        <a href="{{ $scheduleIndexUrl }}" class="breadcrumb-link">{{ ($campaign->is_sticky_campaign ?? false) ? 'Sticky schedule' : 'Schedule campaigns' }}</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="javascript:void(0)" class="breadcrumb-link">Edit {{ $campaign->campaign_no }}</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center gap-2">
                <a href="{{ route('admin.schedule.campaign.show', $campaign->id) }}"
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
        $showLocalClientTab = (Auth::guard('admin')->user()?->isSuperAdmin()
            || (int) ($campaign->admin_id ?? 0) === (int) Auth::guard('admin')->id())
            && ($localClients ?? collect())->isNotEmpty();
        $allowedEditTabs = $showLocalClientTab ? ['campaign', 'keywords', 'local_client'] : ['campaign', 'keywords'];
        $editTab = session('edit_schedule_campaign_tab', 'campaign');
        if (! in_array($editTab, $allowedEditTabs, true)) {
            $editTab = 'campaign';
        }
    @endphp

    <div class="w-full flex flex-wrap justify-between items-start content-card !p-4 !mt-2">
        <h2 class="text-xl capitalize !mb-2 bg-[var(--primary-color)] text-white w-full !p-2 rounded max-w-fit">
            {{ ($campaign->is_sticky_campaign ?? false) ? 'Edit sticky schedule' : 'Edit schedule' }} — {{ $campaign->campaign_no }}
        </h2>
        <div class="w-full flex flex-wrap gap-5 !p-2 !pb-0">
            <button type="button" data-edit-tab="campaign"
                class="edit-campaign-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'campaign' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                <span>Campaign Info</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'campaign' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'campaign' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'campaign' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                    </div>
                    <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $editTab === 'campaign' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                </div>
            </button>
            <button type="button" data-edit-tab="keywords"
                class="edit-campaign-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'keywords' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                <span>Add keywords &amp; Url</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'keywords' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'keywords' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'keywords' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                    </div>
                    <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $editTab === 'keywords' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                </div>
            </button>
            @if ($showLocalClientTab)
                <button type="button" data-edit-tab="local_client"
                    class="edit-campaign-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $editTab === 'local_client' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                    <span>Local client</span>
                    <div class="w-full flex items-center gap-1">
                        <div class="flex items-center gap-1">
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'local_client' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'local_client' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $editTab === 'local_client' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        </div>
                        <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $editTab === 'local_client' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                    </div>
                </button>
            @endif
        </div>

        <div id="edit-campaign-info" data-edit-panel="campaign"
            class="edit-campaign-panel w-full !p-4 duration-500 transition-all {{ $editTab === 'campaign' ? '' : 'hidden opacity-0 translate-y-5' }}">
            <div class="w-full flex flex-col gap-3 !px-2">
                <p class="text-sm text-gray-600">Update the campaign identifier shown in lists and reports.</p>
                <form action="{{ route('admin.schedule.campaign.update', $campaign->id) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="w-full flex flex-col gap-1 !mt-1">
                        <label for="edit-campaign-no" class="text-sm text-gray-700">Campaign #</label>
                        <div class="w-full relative !mb-2">
                            <input id="edit-campaign-no" type="text" name="campaign_no" value="{{ $campaign->campaign_no }}"
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

        <div id="edit-add-keywords-url" data-edit-panel="keywords"
            class="edit-campaign-panel w-full !p-4 duration-500 transition-all {{ $editTab === 'keywords' ? '' : 'hidden opacity-0 translate-y-5' }}">
            @php
                $keywordEditTab = session('edit_schedule_campaign_keywords_tab', $preferredKeywordTab ?? 'batch');
                if (! in_array($keywordEditTab, ['batch', 'multi', 'bulk'], true)) {
                    $keywordEditTab = 'batch';
                }
            @endphp
            <div class="w-full flex flex-wrap gap-5 !p-2 !pb-0">
                <button type="button" data-keywords-edit-tab="batch"
                    class="keywords-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $keywordEditTab === 'batch' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                    <span>Add Keyword &amp; Url</span>
                    <div class="w-full flex items-center gap-1">
                        <div class="flex items-center gap-1">
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $keywordEditTab === 'batch' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $keywordEditTab === 'batch' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $keywordEditTab === 'batch' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        </div>
                        <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $keywordEditTab === 'batch' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                    </div>
                </button>
                <button type="button" data-keywords-edit-tab="multi"
                    class="keywords-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $keywordEditTab === 'multi' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                    <span>Add Multi Level Keyword &amp; Url</span>
                    <div class="w-full flex items-center gap-1">
                        <div class="flex items-center gap-1">
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $keywordEditTab === 'multi' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $keywordEditTab === 'multi' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $keywordEditTab === 'multi' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        </div>
                        <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $keywordEditTab === 'multi' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                    </div>
                </button>
                <button type="button" data-keywords-edit-tab="bulk"
                    class="keywords-edit-tab group flex flex-col gap-3 rounded !p-2 cursor-pointer text-lg w-fit {{ $keywordEditTab === 'bulk' ? 'text-[var(--primary-color)]' : 'duration-300 transition-all hover:text-[var(--primary-color)]' }}">
                    <span>Add Bulk Keyword &amp; Url</span>
                    <div class="w-full flex items-center gap-1">
                        <div class="flex items-center gap-1">
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $keywordEditTab === 'bulk' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $keywordEditTab === 'bulk' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                            <span class="heading-dots w-1 h-1 flex rounded-full duration-300 transition-all {{ $keywordEditTab === 'bulk' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></span>
                        </div>
                        <div class="heading-line w-15 h-1 flex rounded duration-300 transition-all {{ $keywordEditTab === 'bulk' ? 'bg-[var(--primary-color)]' : 'bg-gray-300 group-hover:bg-[var(--primary-color)]' }}"></div>
                    </div>
                </button>
            </div>

            <div data-keywords-edit-panel="batch"
                class="keywords-edit-panel w-full duration-500 transition-all {{ $keywordEditTab === 'batch' ? '' : 'hidden opacity-0 translate-y-5' }}">
                <form action="{{ route('admin.schedule.campaign.bulk.update', $campaign->id) }}" method="post" class="w-full !mt-4" id="bulk-keyword-form">
                    @csrf
                    <div class="w-full flex flex-col gap-2">
                        <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded">
                            Update keywords &amp; URLs (distinct batches)
                        </h3>
                        <p class="text-sm text-gray-600 max-w-4xl">
                            Each batch groups posts that shared the same keyword/URL at creation time.
                            Use <strong>+ Add row</strong> to convert <strong>single link → multiple links</strong> for every post in that batch.
                            Changes are saved to the database and published posts are queued on the remote site.
                            <strong>At least one</strong> row must have both keyword and URL; do not clear every row.
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
                                                <input type="text" name="batch_url[{{ $idx }}][]" value="{{ $pair['url'] }}"
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

            <div data-keywords-edit-panel="multi"
                class="keywords-edit-panel w-full duration-500 transition-all {{ $keywordEditTab === 'multi' ? '' : 'hidden opacity-0 translate-y-5' }}">
                <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mt-4">
                    Add Multi Level Keyword &amp; Url
                </h3>
                <p class="text-sm text-gray-600 max-w-4xl !mt-2">
                    Post quantity: <strong>{{ (int) $postQuantity }}</strong>.
                    Use <strong>Apply for</strong> to assign each box to one or more posts (same as create). The sum of “Apply for” must equal post quantity.
                    Drag row numbers to reorder links. Saving overwrites keyword/URL/media/nofollow for all posts in campaign order.
                </p>

                @if ($postQuantity < 1)
                    <p class="text-gray-500 !mt-4">No campaign articles found.</p>
                @else
                    <form action="{{ route('admin.schedule.campaign.multi.keywords.update', $campaign->id) }}" method="post"
                        class="w-full flex flex-col gap-3 !mt-4" id="edit-multi-keyword-form">
                        @csrf
                        <input type="hidden" name="keywordmethod" value="multiple">
                        <input type="hidden" name="keywordsDataHolder" id="editKeywordsDataHolder" value="">

                        <div class="w-full flex flex-col gap-1 justify-between overflow-hidden overflow-y-auto max-h-[560px] border border-gray-200 rounded-lg !p-3 bg-gray-50"
                            id="edit-multi-level-keyword-url-container">
                            <div class="w-full flex flex-col gap-1 max-h-[420px] overflow-hidden overflow-y-auto"
                                id="edit-multi-key-url-box-parent"></div>

                            <div class="w-full flex flex-wrap justify-end !py-2">
                                <div class="w-full sm:w-1/2 flex justify-between items-center text-sm">
                                    <p>Total <span id="edit-multi-total-box-count">0</span></p>
                                    <a href="javascript:void(0)" class="bg-yellow-400 !p-2 text-sm rounded text-white"
                                        id="edit-add-more-multi-keyword-Url">+Add More</a>
                                </div>
                            </div>
                        </div>

                        <div class="w-full flex flex-wrap justify-between items-center gap-3 !mt-2 !pt-2 border-t border-gray-200">
                            <div class="flex flex-wrap gap-3 items-center">
                                <div class="flex gap-2 items-center">
                                    <input type="checkbox" name="no_follow" id="edit-no-follow" value="1"
                                        {{ ! empty($initialNofollow) ? 'checked' : '' }}>
                                    <label for="edit-no-follow" class="text-sm">No Follow</label>
                                </div>
                            </div>
                            <button type="submit" id="edit-multi-keyword-submit"
                                class="!px-4 !py-3 rounded-lg bg-green-600 text-white hover:opacity-90 disabled:opacity-70 disabled:cursor-not-allowed">
                                Save &amp; sync to remote posts
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <div data-keywords-edit-panel="bulk"
                class="keywords-edit-panel w-full duration-500 transition-all {{ $keywordEditTab === 'bulk' ? '' : 'hidden opacity-0 translate-y-5' }}">
                <h3 class="text-lg w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mt-4">
                    Add Bulk Keyword &amp; Url
                </h3>
                <p class="text-sm text-gray-600 max-w-4xl !mt-2">
                    Paste one URL per line and one keyword per line. This will overwrite current keyword/URL data.
                    Non-empty line count in both boxes must equal post quantity: <strong>{{ (int) $postQuantity }}</strong>.
                </p>
                @if ($postQuantity < 1)
                    <p class="text-gray-500 !mt-4">No campaign articles found.</p>
                @else
                    <form action="{{ route('admin.schedule.campaign.multi.keywords.update', $campaign->id) }}" method="post"
                        class="w-full flex flex-col gap-3 !mt-4" id="edit-bulk-textarea-form">
                        @csrf
                        <input type="hidden" name="keywordmethod" value="multiple">
                        <input type="hidden" name="keywordsDataHolder" id="editBulkKeywordsDataHolder" value="">
                        <div class="w-full flex flex-wrap justify-between bg-gray-100 border border-gray-200 rounded !p-3">
                            <div class="w-[49.5%] flex flex-col gap-2">
                                <div class="flex items-center">
                                    <label for="edit-bulk-urls"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                                        Client Urls
                                    </label>
                                    <span class="text-sm !ml-1 text-gray-500" id="edit-bulk-urls-count">(0)</span>
                                </div>
                                <textarea id="edit-bulk-urls"
                                    class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none"
                                    rows="14" placeholder="https://example.com/page-1"></textarea>
                            </div>
                            <div class="w-[49.5%] flex flex-col gap-2">
                                <div class="flex items-center">
                                    <label for="edit-bulk-keywords"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                                        Client Keywords
                                    </label>
                                    <span class="text-sm !ml-1 text-gray-500" id="edit-bulk-keywords-count">(0)</span>
                                </div>
                                <textarea id="edit-bulk-keywords"
                                    class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none"
                                    rows="14" placeholder="keyword 1"></textarea>
                            </div>
                        </div>
                        <div class="w-full flex flex-wrap justify-between items-center gap-3 !mt-2 !pt-2 border-t border-gray-200">
                            <div class="flex flex-wrap gap-3 items-center">
                                <div class="flex gap-2 items-center">
                                    <input type="checkbox" name="no_follow" id="edit-bulk-no-follow" value="1"
                                        {{ ! empty($initialNofollow) ? 'checked' : '' }}>
                                    <label for="edit-bulk-no-follow" class="text-sm">No Follow</label>
                                </div>
                            </div>
                            <button type="submit" id="edit-bulk-textarea-submit"
                                class="!px-4 !py-3 rounded-lg bg-green-600 text-white hover:opacity-90 disabled:opacity-70 disabled:cursor-not-allowed">
                                Save &amp; sync to remote posts
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        @if ($showLocalClientTab)
            <div id="edit-local-client" data-edit-panel="local_client"
                class="edit-campaign-panel w-full !p-4 duration-500 transition-all {{ $editTab === 'local_client' ? '' : 'hidden opacity-0 translate-y-5' }}">
                @include('admin.campaigns.partials.local-client-billing-edit')
            </div>
        @endif
    </div>

    @push('scripts')
    @if ($postQuantity >= 1)
        <script>
            window.__editMultiKeywordConfig = {
                postCount: {{ (int) $postQuantity }},
                initial: { boxes: @json($multiLevelBoxes) },
            };
        </script>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script src="{{ asset('js/edit-campaign-multi-keyword.js') }}"></script>
    @endif
    <script>
    (function() {
        var initialTab = @json($editTab);
        var tabBtns = document.querySelectorAll('.edit-campaign-tab');
        var panels = document.querySelectorAll('.edit-campaign-panel');

        function setEditCampaignTab(tab) {
            tabBtns.forEach(function(btn) {
                var name = btn.getAttribute('data-edit-tab');
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
                var name = panel.getAttribute('data-edit-panel');
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
                setEditCampaignTab(btn.getAttribute('data-edit-tab'));
            });
        });
        setEditCampaignTab(initialTab);

        var keywordTabBtns = document.querySelectorAll('.keywords-edit-tab');
        var keywordPanels = document.querySelectorAll('.keywords-edit-panel');
        var initialKeywordTab = @json($keywordEditTab);
        function setKeywordsEditTab(tab) {
            keywordTabBtns.forEach(function(btn) {
                var name = btn.getAttribute('data-keywords-edit-tab');
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
            keywordPanels.forEach(function(panel) {
                var name = panel.getAttribute('data-keywords-edit-panel');
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
        keywordTabBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                setKeywordsEditTab(btn.getAttribute('data-keywords-edit-tab'));
            });
        });
        setKeywordsEditTab(initialKeywordTab);

        var bulkForm = document.getElementById('bulk-keyword-form');
        var bulkSubmit = document.getElementById('bulk-update-submit-btn');
        if (bulkForm && bulkSubmit) {
            bulkForm.addEventListener('submit', function() {
                bulkSubmit.disabled = true;
                bulkSubmit.textContent = 'Updating...';
            });
        }
        if (bulkSubmit && document.querySelector('.bg-green-100')) {
            bulkSubmit.disabled = false;
            bulkSubmit.textContent = 'Update batches (DB + remote)';
        } else if (bulkSubmit && document.querySelector('.bg-red-100')) {
            bulkSubmit.disabled = false;
            bulkSubmit.textContent = 'Update batches (DB + remote)';
        }

        var multiForm = document.getElementById('edit-multi-keyword-form');
        var multiSubmit = document.getElementById('edit-multi-keyword-submit');
        var statusText = document.getElementById('campaign-status-text');
        var statusBox = document.getElementById('campaign-status-box');
        var normalStatus = "{{ ucfirst($campaign->status ?? '') }}";

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
            if (multiSubmit) {
                multiSubmit.disabled = false;
                multiSubmit.textContent = 'Save & sync to remote posts';
            }
            setTimeout(function() {
                statusText.textContent = normalStatus;
                statusBox.classList.remove('!bg-green-50', '!border-green-300');
                statusBox.classList.add('bg-gray-50', 'border-gray-200');
            }, 5000);
        } else if (statusText && statusBox && multiSubmit && document.querySelector('.bg-red-100')) {
            statusText.textContent = normalStatus;
            multiSubmit.disabled = false;
            multiSubmit.textContent = 'Save & sync to remote posts';
        }

        var bulkTextareaForm = document.getElementById('edit-bulk-textarea-form');
        var bulkTextareaSubmit = document.getElementById('edit-bulk-textarea-submit');
        var bulkUrls = document.getElementById('edit-bulk-urls');
        var bulkKeywords = document.getElementById('edit-bulk-keywords');
        var bulkHolder = document.getElementById('editBulkKeywordsDataHolder');
        var bulkNoFollow = document.getElementById('edit-bulk-no-follow');
        var urlsCountEl = document.getElementById('edit-bulk-urls-count');
        var keywordsCountEl = document.getElementById('edit-bulk-keywords-count');
        var postQty = {{ (int) $postQuantity }};
        function toNonEmptyLines(v) {
            return (v || '').split(/\r\n|\r|\n/).map(function(s){ return s.trim(); }).filter(function(s){ return s !== ''; });
        }
        function refreshBulkTextareaCounts() {
            var u = toNonEmptyLines(bulkUrls ? bulkUrls.value : '').length;
            var k = toNonEmptyLines(bulkKeywords ? bulkKeywords.value : '').length;
            if (urlsCountEl) urlsCountEl.textContent = '(' + u + ')';
            if (keywordsCountEl) keywordsCountEl.textContent = '(' + k + ')';
        }
        if (bulkUrls) bulkUrls.addEventListener('input', refreshBulkTextareaCounts);
        if (bulkKeywords) bulkKeywords.addEventListener('input', refreshBulkTextareaCounts);
        refreshBulkTextareaCounts();
        if (bulkTextareaForm && bulkTextareaSubmit && bulkHolder) {
            bulkTextareaForm.addEventListener('submit', function(e) {
                var urls = toNonEmptyLines(bulkUrls ? bulkUrls.value : '');
                var kws = toNonEmptyLines(bulkKeywords ? bulkKeywords.value : '');
                if (urls.length !== kws.length) {
                    e.preventDefault();
                    alert('Bulk URLs and Bulk Keywords line counts must match.');
                    return;
                }
                if (urls.length !== postQty) {
                    e.preventDefault();
                    alert('Bulk lines must equal Post Quantity (' + postQty + ').');
                    return;
                }
                var nf = bulkNoFollow && bulkNoFollow.checked;
                var payload = [];
                for (var i = 0; i < postQty; i++) {
                    payload.push({
                        media: '',
                        url: [urls[i]],
                        keyword: [kws[i]],
                        nofollow: nf
                    });
                }
                bulkHolder.value = JSON.stringify(payload);
                bulkTextareaSubmit.disabled = true;
                bulkTextareaSubmit.textContent = 'Updating...';
            });
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
