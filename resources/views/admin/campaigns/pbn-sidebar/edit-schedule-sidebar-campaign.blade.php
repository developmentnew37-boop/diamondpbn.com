@extends('admin.layout.layout')

@section('title', 'Edit Schedule Sidebar Campaign')

@section('main-content')

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

    <div class="w-full content-card !p-4 !mt-3">
        <h2 class="text-xl w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
            Update campaign number
        </h2>
        <form action="{{ route('admin.schedule.sidebar.campaign.update', $campaign->id) }}" method="post">
            @csrf
            @method('PUT')
            <div class="flex flex-col gap-2 max-w-md">
                <input type="text" name="campaign_no" value="{{ old('campaign_no', $campaign->campaign_no) }}"
                    class="w-full rounded !p-3 text-sm border border-gray-300 focus:border-[var(--primary-color)]">
                @error('campaign_no')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                <button type="submit" class="!py-2 !px-4 rounded bg-[var(--primary-color)] text-white w-fit">Update campaign no</button>
            </div>
        </form>
    </div>

    <div class="w-full content-card !mt-4 !p-4">
        <h2 class="text-xl w-fit font-medium bg-[var(--primary-color)] text-white !px-3 !py-2 rounded !mb-2">
            Update keywords and URLs (by batch)
        </h2>
        <p class="text-sm text-gray-600 !mb-4">
            Each batch groups links with the same keyword and URL. Updating a batch updates that keyword/URL on all remote sites that have it.
        </p>

        @if (empty($distinctBatches))
            <p class="text-gray-500">No published links to edit. Only successfully published tasks (on remote) appear as batches.</p>
            <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}" class="inline-block !mt-2 !px-4 !py-2 bg-gray-200 rounded">Back</a>
        @else
            <form action="{{ route('admin.schedule.sidebar.campaign.bulk.update', $campaign->id) }}" method="POST" class="w-full">
                @csrf
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
                        Update batches and sync to remote
                    </button>
                    <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}"
                        class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        @endif
    </div>

@endsection
