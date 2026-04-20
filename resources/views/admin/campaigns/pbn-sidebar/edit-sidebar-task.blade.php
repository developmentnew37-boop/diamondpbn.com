@extends('admin.layout.layout')

@section('title', 'Edit Sidebar Task - Admin Panel')

@section('main-content')

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="w-full md:flex-1 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Edit Sidebar Link</h2>
                <div class="breadcrumb flex-wrap gap-y-1">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.sidebar.campaign.index') }}" class="breadcrumb-link">Sidebar Campaigns</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.sidebar.campaign.show', $task->sidebar_campaign_id) }}" class="breadcrumb-link break-all">{{ $task->campaign->campaign_no ?? 'Campaign' }}</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Edit keyword/link</span>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-auto flex flex-wrap justify-start md:justify-end items-center md:shrink-0">
                <a href="{{ route('admin.sidebar.campaign.show', $task->sidebar_campaign_id) }}"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm w-full sm:w-auto justify-center whitespace-nowrap">Back</a>
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
        <p class="text-sm text-gray-600 !mb-4">
            Domain: <strong>{{ optional($task->domainRow?->domain)->name ?? '-' }}</strong>
            &nbsp;|&nbsp; Remote ID: <strong>{{ $task->remote_id }}</strong>
        </p>
        <p class="text-xs text-gray-500 !mb-4">
            This will update the keyword and link on the remote site (via blogroll API) and in our database.
        </p>

        <form action="{{ route('admin.sidebar.campaign.update.task', $task->id) }}" method="POST" class="flex flex-col gap-4 max-w-xl w-full">
            @csrf
            <div>
                <label for="keyword" class="block text-sm font-medium text-gray-700 !mb-1">Keyword <span class="text-red-500">*</span></label>
                <input type="text" name="keyword" id="keyword" value="{{ old('keyword', $task->linkRow?->anchor_keyword ?? '') }}"
                    class="w-full border border-gray-300 rounded !px-3 !py-2 focus:ring focus:ring-blue-200 focus:border-blue-500"
                    required maxlength="500">
                @error('keyword')
                    <p class="text-red-500 text-xs !mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="link" class="block text-sm font-medium text-gray-700 !mb-1">Link (URL) <span class="text-red-500">*</span></label>
                <input type="url" name="link" id="link" value="{{ old('link', $task->linkRow?->target_url ?? '') }}"
                    class="w-full border border-gray-300 rounded !px-3 !py-2 focus:ring focus:ring-blue-200 focus:border-blue-500"
                    required maxlength="500">
                @error('link')
                    <p class="text-red-500 text-xs !mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <button type="submit" class="!px-4 !py-2 bg-[var(--primary-color)] text-white rounded hover:opacity-90">
                    Update on remote &amp; DB
                </button>
                <a href="{{ route('admin.sidebar.campaign.show', $task->sidebar_campaign_id) }}"
                    class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-center">Cancel</a>
            </div>
        </form>
    </div>

@endsection
