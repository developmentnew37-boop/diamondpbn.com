@extends('admin.layout.layout')

@section('title', 'Replace Campaign Domain')

@section('main-content')
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="page-title">Replace Campaign Domain</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ isset($showRouteName) ? route($showRouteName, $campaign->id) : route('admin.dashboard') }}" class="breadcrumb-link">
                            {{ $campaign->campaign_no }}
                        </a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">Replace domain</div>
                </div>
            </div>
            <a href="{{ route($showRouteName, $campaign->id) }}"
                class="inline-flex items-center rounded bg-gray-200 !px-3 !py-2 text-sm hover:bg-gray-300">
                Back to campaign
            </a>
        </div>
    </div>

    <div class="content-card w-full !p-5">
        @if (session('cus__error'))
            <div class="rounded bg-red-100 text-red-700 !p-4 !mb-4">{{ session('cus__error') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded bg-red-100 text-red-700 !p-4 !mb-4">
                <ul class="list-disc !ml-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 !mb-5">
            <div class="rounded border border-gray-200 !p-3">
                <div class="text-xs text-gray-500">Campaign</div>
                <div class="font-semibold">{{ $campaign->campaign_no }}</div>
            </div>
            <div class="rounded border border-gray-200 !p-3">
                <div class="text-xs text-gray-500">{{ $taskLabel }}</div>
                <div class="font-semibold">#{{ $task->id }} · {{ ucfirst($task->status) }}</div>
            </div>
            <div class="rounded border border-gray-200 !p-3">
                <div class="text-xs text-gray-500">Current domain</div>
                <div class="font-semibold">{{ $oldDomain->name }}</div>
            </div>
        </div>

        <form method="GET" action="{{ route($createRoute, $task) }}"
            class="flex flex-wrap gap-2 !mb-4">
            <input type="search" name="search" value="{{ $search ?? request('search') }}"
                placeholder="Look up a domain by name, e.g. example.com"
                class="min-w-[260px] flex-1 rounded border border-gray-200 bg-gray-50 !px-3 !py-2">
            <button type="submit"
                class="rounded bg-gray-800 text-white !px-4 !py-2 hover:bg-gray-700">Look up</button>
        </form>

        @if (! empty($searchFeedback))
            @php
                $feedbackClass = match ($searchFeedback['type']) {
                    'eligible' => 'border-green-200 bg-green-50 text-green-800',
                    'ineligible' => 'border-amber-200 bg-amber-50 text-amber-900',
                    default => 'border-red-200 bg-red-50 text-red-800',
                };
            @endphp
            <div class="rounded border !p-4 !mb-4 {{ $feedbackClass }}">
                @if (! empty($searchFeedback['domain']))
                    <div class="font-semibold !mb-1">{{ $searchFeedback['domain']->name }}</div>
                @endif
                <p class="text-sm">{{ $searchFeedback['message'] }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route($storeRoute, $task) }}">
            @csrf
            <input type="hidden" name="expected_old_domain_id" value="{{ $oldDomain->id }}">
            <input type="hidden" name="request_uuid" value="{{ old('request_uuid', $requestUuid) }}">

            <label for="new_domain_name" class="block font-medium !mb-2">Replacement domain</label>
            <input type="text" id="new_domain_name" name="new_domain_name"
                value="{{ old('new_domain_name', $searchedDomain?->name ?? ($search ?? request('search'))) }}"
                placeholder="Enter domain name, e.g. example.com"
                class="w-full rounded border border-gray-200 bg-gray-50 !px-3 !py-3 !mb-2">
            <p class="text-sm text-gray-500 !mb-4">
                Enter the exact domain name. It must already exist under Domains and must not already be
                attached to this campaign. After replacement the task is queued to publish on the new domain.
            </p>

            <label for="reason" class="block font-medium !mb-2">Reason for replacement <span class="text-sm font-normal text-gray-500">(optional)</span></label>
            <textarea id="reason" name="reason" rows="4" maxlength="2000"
                class="w-full rounded border border-gray-200 bg-gray-50 !px-3 !py-3 !mb-4"
                placeholder="Optional note about why this domain is being replaced.">{{ old('reason') }}</textarea>

            <button type="submit"
                class="rounded bg-[var(--primary-color)] text-white !px-5 !py-3 disabled:cursor-not-allowed disabled:opacity-50">
                Replace and queue task
            </button>
        </form>
    </div>
@endsection
