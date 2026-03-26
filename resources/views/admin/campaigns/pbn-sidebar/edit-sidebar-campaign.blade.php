@extends('admin.layout.layout')

@section('title', 'Bulk Edit Sidebar Links - Admin Panel')

@section('main-content')

    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Bulk Edit Sidebar Links</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.sidebar.campaign.index') }}" class="breadcrumb-link">PBN Blogroll</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}" class="breadcrumb-link">{{ $campaign->campaign_no }}</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Bulk edit links</span>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm">Back to campaign</a>
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

    <div class="w-full content-card !mt-3">
        <h2 class="text-xl w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
            Update keywords &amp; URLs (by batch)
        </h2>
        <p class="text-sm text-gray-600 !mb-4">
            Each batch groups links with the <strong>same keyword and URL</strong>. Updating a batch updates that keyword/URL on <strong>all remote sites</strong> that have it. 
        </p>

        @if (empty($distinctBatches))
            <p class="text-gray-500">No published links to edit. Only successfully published tasks (on remote) appear as batches.</p>
            <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
        @else
            <form action="{{ route('admin.sidebar.campaign.update', $campaign->id) }}" method="POST" class="w-full">
                @csrf
                @method('PUT')
                <div class="w-full flex flex-col gap-4">
                    @foreach ($distinctBatches as $idx => $batch)
                        <div class="border border-gray-200 rounded-lg !p-4 bg-gray-50">
                            <input type="hidden" name="batch_representative_link_id[]" value="{{ $batch['representative_link_id'] }}">
                            <div class="flex items-center justify-between !mb-2">
                                <span class="font-medium text-gray-700">Batch {{ $idx + 1 }}</span>
                                <span class="text-sm text-gray-500">{{ $batch['count'] }} link(s) on remote</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Keyword</label>
                                    <input type="text"
                                        name="batch_keyword[]"
                                        value="{{ old("batch_keyword.{$idx}", $batch['keyword']) }}"
                                        class="w-full border border-gray-300 rounded !px-2 !py-2 text-sm" maxlength="500" placeholder="Keyword">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">URL</label>
                                    <input type="url"
                                        name="batch_url[]"
                                        value="{{ old("batch_url.{$idx}", $batch['url']) }}"
                                        class="w-full border border-gray-300 rounded !px-2 !py-2 text-sm" maxlength="500" placeholder="https://...">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="!mt-4 flex gap-2">
                    <button type="submit" class="!px-4 !py-2 bg-[var(--primary-color)] text-white rounded hover:opacity-90">
                        Update batches &amp; sync to remote
                    </button>
                    <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}"
                        class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        @endif
    </div>

@endsection
