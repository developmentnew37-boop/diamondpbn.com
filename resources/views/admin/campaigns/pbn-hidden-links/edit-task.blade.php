@extends('admin.layout.layout')

@section('title', 'Edit Hidden Link Task - Admin Panel')

@section('main-content')

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3 min-w-0">
                <h2 class="page-title !mb-0 min-w-0 shrink leading-tight">Edit Hidden Link</h2>
                <a href="{{ route('admin.hidden.link.campaign.show', $task->hidden_links_campaigns_id) }}"
                    class="inline-flex items-center justify-center gap-2 shrink-0 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm whitespace-nowrap"
                    aria-label="Back to campaign view">Back</a>
            </div>
            <nav class="flex flex-nowrap items-center gap-1.5 text-sm text-gray-600 w-full min-w-0 overflow-x-auto whitespace-nowrap pb-1"
                aria-label="Breadcrumb">
                <span class="inline-flex items-center gap-x-1.5 shrink-0">
                    <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link shrink-0">Dashboard</a>
                    <span class="text-gray-400 shrink-0" aria-hidden="true">›</span>
                </span>
                <span class="inline-flex items-center gap-x-1.5 shrink-0">
                    <a href="{{ route('admin.hidden.link.campaign.index') }}" class="breadcrumb-link">PBN Hidden Links</a>
                    <span class="text-gray-400 shrink-0" aria-hidden="true">›</span>
                </span>
                <span class="inline-flex items-center gap-x-1.5 shrink-0 max-w-full">
                    <a href="{{ route('admin.hidden.link.campaign.show', $task->hidden_links_campaigns_id) }}"
                        class="breadcrumb-link font-mono text-xs sm:text-sm whitespace-nowrap"
                        title="{{ $task->campaign->campaign_no ?? 'Campaign' }}">{{ $task->campaign->campaign_no ?? 'Campaign' }}</a>
                    <span class="text-gray-400 shrink-0" aria-hidden="true">›</span>
                </span>
                <span class="text-gray-600 shrink-0">Edit keyword/link</span>
            </nav>
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

    <div class="w-full content-card !mt-3">
        <p class="text-sm text-gray-600 !mb-4">
            Domain: <strong>{{ optional($task->domainRow?->domain)->name ?? '-' }}</strong>
            &nbsp;|&nbsp; Remote ID: <strong>{{ $task->remote_id }}</strong>
        </p>
        <p class="text-xs text-gray-500 !mb-4">
            This will update the keyword and link on the remote site (hidden-links API) and in our database.
        </p>

        <form action="{{ route('admin.hidden.link.campaign.update.task', $task->id) }}" method="POST" class="flex flex-col gap-4 max-w-xl">
            @csrf
            <div>
                <label for="keyword" class="block text-sm font-medium text-gray-700 !mb-1">Keyword <span class="text-red-500">*</span></label>
                <input type="text" name="keyword" id="keyword" value="{{ old('keyword', $task->linkRow?->anchor_keyword ?? '') }}"
                    class="w-full border border-gray-300 rounded !px-3 !py-2" required maxlength="500">
                @error('keyword')
                    <p class="text-red-500 text-xs !mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="link" class="block text-sm font-medium text-gray-700 !mb-1">Link (URL) <span class="text-red-500">*</span></label>
                <input type="url" name="link" id="link" value="{{ old('link', $task->linkRow?->target_url ?? '') }}"
                    class="w-full border border-gray-300 rounded !px-3 !py-2" required maxlength="500">
                @error('link')
                    <p class="text-red-500 text-xs !mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                <button type="submit" class="!px-4 !py-2 bg-[var(--primary-color)] text-white rounded hover:opacity-90 text-center">Update on remote &amp; DB</button>
                <a href="{{ route('admin.hidden.link.campaign.show', $task->hidden_links_campaigns_id) }}"
                    class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-center">Cancel</a>
            </div>
        </form>
    </div>

@endsection
