@extends('admin.layout.layout')

@section('title', 'Transfer Domains - Step 1')

@push('style')
    <style>
        .extension-badge {
            display: inline-block;
            padding: 6px 14px;
            margin: 4px;
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            color: #374151;
        }

        .extension-badge:hover {
            background-color: #e5e7eb;
            border-color: #d1d5db;
        }

        .extension-badge.active {
            background-color: var(--primary-color, #f97316);
            color: white;
            border-color: var(--primary-color, #f97316);
        }

        .domain-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            transition: all 0.2s ease;
            background-color: white;
        }

        .domain-card:hover {
            border-color: var(--primary-color, #f97316);
        }

        .domain-card.selected {
            border-color: #10b981;
            background-color: #f0fdf4;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 24px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            background-color: var(--primary-color, #f97316);
            color: white;
        }

        .step-circle.inactive {
            background-color: #e5e7eb;
            color: #6b7280;
        }

        .selected-count {
            display: inline-block;
            padding: 4px 12px;
            background-color: #10b981;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .page-header-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 42px;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.25;
            border-radius: 0.375rem;
            white-space: nowrap;
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        .page-header-btn-secondary {
            background-color: #fff;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .page-header-btn-secondary:hover {
            background-color: var(--primary-color);
            color: #fff;
        }

        @media (max-width: 767px) {
            .transfer-page-header .page-title {
                font-size: 1.375rem;
                line-height: 1.3;
                margin-bottom: 0.25rem;
            }

            .transfer-page-header .breadcrumb {
                font-size: 0.8125rem;
                line-height: 1.4;
            }

            .transfer-page-header .page-header-actions {
                width: 100%;
            }

            .transfer-page-header .page-header-btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('main-content')
    <div class="page-header transfer-page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 md:gap-4 md:flex-row md:items-center md:justify-between">
            <div class="w-full md:flex-1 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Transfer Domains</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.pending-domains.index') }}" class="breadcrumb-link">Pending Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Step 1: Select Domains</a>
                    </div>
                </div>
            </div>
            <div class="page-header-actions w-full md:w-auto md:shrink-0">
                <a href="{{ route('admin.pending-domains.index') }}"
                    class="page-header-btn page-header-btn-secondary w-full md:w-auto">
                    <span class="material-symbols-outlined !text-base">arrow_back</span>
                    <span>Back to Pending Domains</span>
                </a>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-col gap-2">
        @if (session('success'))
            <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                <span class="font-medium">{{ session('error') }}</span>
            </div>
        @endif
    </div>

    <div class="step-indicator !mt-4">
        <div class="flex items-center gap-2">
            <div class="step-circle">1</div>
            <span class="text-sm font-semibold text-gray-800">Select Domains</span>
        </div>
        <span class="text-gray-400">→</span>
        <div class="flex items-center gap-2">
            <div class="step-circle inactive">2</div>
            <span class="text-sm font-medium text-gray-400">Choose Category</span>
        </div>
    </div>

    <div class="w-full content-card !mt-4">
        <div class="flex flex-col gap-4">
            <div>
                <h3 class="text-base font-semibold text-gray-800 !mb-3">Filter by Extension</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.transfer-domains.step1') }}"
                        class="extension-badge {{ !request('extension') ? 'active' : '' }}">
                        All Extensions
                    </a>
                    @foreach ($extensions as $ext)
                        <a href="{{ route('admin.transfer-domains.step1', ['extension' => $ext]) }}"
                            class="extension-badge {{ request('extension') === $ext ? 'active' : '' }}">
                            .{{ $ext }} ({{ $extensionCounts->get($ext, 0) }})
                        </a>
                    @endforeach
                </div>
            </div>

            <hr class="border-gray-200">

            <form id="transferForm" action="{{ route('admin.transfer-domains.step2') }}" method="POST">
                @csrf

                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 !mb-4">
                    <h3 class="text-base font-semibold text-gray-800">
                        Select Domains to Transfer
                        <span class="selected-count" id="selectedCount">0 selected</span>
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="selectAll()"
                            class="flex !p-2 !py-2 text-sm font-normal justify-center duration-300 bg-gray-800 hover:bg-gray-900 text-white rounded">
                            Select All
                        </button>
                        <button type="button" onclick="deselectAll()"
                            class="flex !p-2 !py-2 text-sm font-normal justify-center duration-300 bg-gray-400 hover:bg-gray-500 text-white rounded">
                            Deselect All
                        </button>
                    </div>
                </div>

                @if ($pendingDomains->isEmpty())
                    <div class="text-center !py-12">
                        <p class="text-gray-500 text-base">No pending domains found</p>
                        @if (request('extension'))
                            <p class="text-gray-400 text-sm !mt-2">
                                <a href="{{ route('admin.transfer-domains.step1') }}" class="text-blue-600 hover:underline">View all extensions</a>
                            </p>
                        @else
                            <p class="text-gray-400 text-sm !mt-2">Domains submitted via webhook will appear here.</p>
                        @endif
                    </div>
                @else
                    <div id="domainList" class="flex flex-col gap-3">
                        @foreach ($pendingDomains as $domain)
                            <div class="domain-card" data-domain-id="{{ $domain->id }}">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex items-start gap-3 flex-grow min-w-0">
                                        <input type="checkbox"
                                            name="domain_ids[]"
                                            value="{{ $domain->id }}"
                                            class="domain-checkbox !mt-1 w-4 h-4 rounded cursor-pointer"
                                            onchange="updateSelection()">

                                        <div class="flex-grow min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="text-sm font-semibold text-gray-800 break-all">
                                                    {{ $domain->domain_name }}
                                                </h4>
                                                @if ($domain->extension)
                                                    <span class="!px-2 !py-0.5 bg-gray-100 text-gray-600 text-xs rounded">.{{ $domain->extension }}</span>
                                                @endif
                                                <span class="!px-2 !py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded-full">Pending</span>
                                                @if (!$domain->viewed)
                                                    <span class="!px-2 !py-0.5 bg-blue-100 text-blue-800 text-xs rounded-full">New</span>
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap items-center gap-3 !mt-2 text-xs text-gray-500">
                                                <span>API Key: ••••••••••••</span>
                                                <span>{{ $domain->created_at->diffForHumans() }}</span>
                                                @if ($domain->webhookSecret)
                                                    <span>{{ $domain->webhookSecret->name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <a href="{{ route('admin.pending-domains.show', $domain->id) }}"
                                        class="!px-3 !py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded text-xs transition-all whitespace-nowrap">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="!mt-6 flex justify-end">
                        <button type="submit"
                            id="continueBtn"
                            class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 bg-black hover:bg-[var(--primary-color)] text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                            Continue to Step 2 →
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.domain-checkbox:checked');
            const count = checkboxes.length;
            const continueBtn = document.getElementById('continueBtn');
            const selectedCount = document.getElementById('selectedCount');

            if (selectedCount) {
                selectedCount.textContent = count + ' selected';
            }
            if (continueBtn) {
                continueBtn.disabled = count === 0;
            }

            document.querySelectorAll('.domain-card').forEach(card => {
                const checkbox = card.querySelector('.domain-checkbox');
                if (checkbox && checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }

        function selectAll() {
            document.querySelectorAll('.domain-checkbox').forEach(cb => cb.checked = true);
            updateSelection();
        }

        function deselectAll() {
            document.querySelectorAll('.domain-checkbox').forEach(cb => cb.checked = false);
            updateSelection();
        }

        document.getElementById('transferForm')?.addEventListener('submit', function(e) {
            if (document.querySelectorAll('.domain-checkbox:checked').length === 0) {
                e.preventDefault();
                alert('Please select at least one domain to transfer');
            }
        });
    </script>
@endpush
