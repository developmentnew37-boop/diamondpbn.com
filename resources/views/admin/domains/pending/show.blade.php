@extends('admin.layout.layout')

@push('style')
    <style>
        .api-key-display {
            font-family: 'Courier New', monospace;
            background: #f9fafb;
            padding: 12px;
            border-radius: 4px;
            font-size: 13px;
            word-break: break-all;
            border: 1px solid #e5e7eb;
        }

        .info-card {
            border-left: 4px solid var(--primary-color);
        }

        .copy-success {
            background-color: #10b981 !important;
            color: white !important;
            border-color: #10b981 !important;
        }
    </style>
@endpush

@section('title', 'Pending Domain Details')

@section('main-content')
    {{-- Breadcrumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Pending Domain Details</h2>
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
                        <a href="#" class="breadcrumb-link">Details</a>
                    </div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end items-center gap-2">
                <a href="{{ route('admin.pending-domains.index') }}"
                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded">
                    Back to List
                </a>
            </div>
        </div>
    </div>

    {{-- Success/Error Messages --}}
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

    {{-- Domain Information --}}
    <div class="w-full flex flex-col lg:flex-row gap-4 !mt-4">
        <div class="flex-1 lg:w-2/3">
            <div class="w-full content-card info-card">
                <div class="flex flex-col gap-5">
                    <h2 class="text-xl font-semibold capitalize">Domain Information</h2>

                    <div class="grid grid-cols-1 gap-4">
                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="w-full sm:w-1/3">
                                <strong class="text-sm text-gray-700">Domain Name:</strong>
                            </div>
                            <div class="w-full sm:w-2/3">
                                <a href="{{ $pendingDomain->domain_url }}" target="_blank" rel="noopener"
                                    class="text-blue-600 hover:text-blue-800 break-all text-sm">
                                    {{ $pendingDomain->domain_name }}
                                    <svg class="inline w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="w-full sm:w-1/3">
                                <strong class="text-sm text-gray-700">API Key:</strong>
                            </div>
                            <div class="w-full sm:w-2/3">
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <div class="api-key-display flex-grow">
                                        {{ $pendingDomain->api_key }}
                                    </div>
                                    <button class="!px-3 !py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 rounded transition-all"
                                        onclick="copyApiKey('{{ $pendingDomain->api_key }}', this)">
                                        Copy
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="w-full sm:w-1/3">
                                <strong class="text-sm text-gray-700">Status:</strong>
                            </div>
                            <div class="w-full sm:w-2/3">
                                @if ($pendingDomain->status === 'pending')
                                    <span class="!px-3 !py-1 inline-flex text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        Pending Review
                                    </span>
                                @elseif($pendingDomain->status === 'approved')
                                    <span class="!px-3 !py-1 inline-flex text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                        Approved
                                    </span>
                                @else
                                    <span class="!px-3 !py-1 inline-flex text-sm font-semibold rounded-full bg-red-100 text-red-800">
                                        Rejected
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="w-full sm:w-1/3">
                                <strong class="text-sm text-gray-700">Viewed:</strong>
                            </div>
                            <div class="w-full sm:w-2/3">
                                @if ($pendingDomain->viewed)
                                    <span class="!px-3 !py-1 inline-flex text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Viewed
                                    </span>
                                @else
                                    <span class="!px-3 !py-1 inline-flex text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                        New/Unviewed
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="w-full sm:w-1/3">
                                <strong class="text-sm text-gray-700">Webhook Secret:</strong>
                            </div>
                            <div class="w-full sm:w-2/3">
                                <a href="{{ route('admin.webhook-secrets.edit', $pendingDomain->webhookSecret->id) }}"
                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                    {{ $pendingDomain->webhookSecret->name }}
                                </a>
                                <span class="!ml-2 !px-2 !py-1 text-xs font-semibold rounded-full {{ $pendingDomain->webhookSecret->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $pendingDomain->webhookSecret->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        @if ($pendingDomain->notes)
                            <div class="flex flex-col sm:flex-row gap-2">
                                <div class="w-full sm:w-1/3">
                                    <strong class="text-sm text-gray-700">Notes:</strong>
                                </div>
                                <div class="w-full sm:w-2/3">
                                    <div class="!p-3 bg-gray-100 border border-gray-200 rounded text-sm">
                                        {{ $pendingDomain->notes }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="w-full sm:w-1/3">
                                <strong class="text-sm text-gray-700">Submitted:</strong>
                            </div>
                            <div class="w-full sm:w-2/3 text-sm">
                                {{ $pendingDomain->created_at->format('Y-m-d H:i:s') }}
                                <span class="text-gray-500">({{ $pendingDomain->created_at->diffForHumans() }})</span>
                            </div>
                        </div>

                        @if ($pendingDomain->approved_at)
                            <div class="flex flex-col sm:flex-row gap-2">
                                <div class="w-full sm:w-1/3">
                                    <strong class="text-sm text-gray-700">Approved At:</strong>
                                </div>
                                <div class="w-full sm:w-2/3 text-sm">
                                    {{ $pendingDomain->approved_at->format('Y-m-d H:i:s') }}
                                </div>
                            </div>
                        @endif

                        @if ($pendingDomain->rejected_at)
                            <div class="flex flex-col sm:flex-row gap-2">
                                <div class="w-full sm:w-1/3">
                                    <strong class="text-sm text-gray-700">Rejected At:</strong>
                                </div>
                                <div class="w-full sm:w-2/3 text-sm">
                                    {{ $pendingDomain->rejected_at->format('Y-m-d H:i:s') }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 lg:w-1/3">
            {{-- Actions Card --}}
            @if ($pendingDomain->status === 'pending')
                <div class="w-full content-card">
                    <div class="flex flex-col gap-4">
                        <h2 class="text-xl font-semibold capitalize">Actions</h2>

                        <a href="{{ route('admin.transfer-domains.step2', ['domain_ids' => [$pendingDomain->id]]) }}"
                            class="flex !p-2 !py-3 text-sm font-normal justify-center items-center duration-300 bg-green-600 hover:bg-green-700 text-white rounded">
                            <svg class="w-4 h-4 !mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                            Transfer to Domains
                        </a>

                        <hr class="border-gray-200">

                        <button type="button" class="flex !p-2 !py-3 text-sm font-normal justify-center items-center duration-300 bg-red-600 hover:bg-red-700 text-white rounded"
                            onclick="showRejectModal()">
                            <svg class="w-4 h-4 !mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Reject Domain
                        </button>
                    </div>
                </div>
            @endif

            {{-- Information Card --}}
            <div class="w-full content-card !mt-4">
                <div class="flex flex-col gap-3">
                    <h2 class="text-xl font-semibold capitalize">What Happens Next?</h2>

                    @if ($pendingDomain->status === 'pending')
                        <div class="text-sm">
                            <p class="!mb-2 font-semibold text-gray-800">To activate this domain:</p>
                            <ul class="!mb-4 !ml-4 space-y-1 list-disc text-gray-700">
                                <li>Use <strong>Transfer to Domains</strong> to assign a category</li>
                                <li>The system will verify the WordPress plugin connection</li>
                                <li>Once transferred, the domain appears in your inventory</li>
                            </ul>

                            <p class="!mb-2 font-semibold text-gray-800">If you reject:</p>
                            <ul class="!ml-4 space-y-1 list-disc text-gray-700">
                                <li>Domain will be marked as rejected</li>
                                <li>Will not appear in your domains list</li>
                                <li>Record remains for audit purposes</li>
                            </ul>
                        </div>
                    @elseif($pendingDomain->status === 'approved')
                        <div class="!p-4 bg-green-50 border border-green-200 rounded text-green-800 text-sm">
                            This domain has been approved and added to your domains list.
                        </div>
                    @else
                        <div class="!p-4 bg-red-50 border border-red-200 rounded text-red-800 text-sm">
                            This domain has been rejected and will not be added to your domains list.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('popup')
    {{-- Reject Modal --}}
    <div id="rejectModal"
        class="fixed inset-0 z-[1100] hidden items-center justify-center p-4 bg-black/50"
        role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg mx-auto overflow-hidden">
            <form action="{{ route('admin.pending-domains.reject', $pendingDomain->id) }}" method="POST">
                @csrf
                <div class="!p-6">
                    <div class="flex items-start gap-3 !mb-4">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-red-600">block</span>
                        </div>
                        <div class="min-w-0">
                            <h3 id="rejectModalTitle" class="text-lg font-semibold text-gray-900">Reject Domain</h3>
                            <p class="text-sm text-gray-500">This domain will not be added to your inventory.</p>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 !mb-3">Are you sure you want to reject this domain?</p>
                    <div class="!p-3 bg-yellow-50 border border-yellow-200 rounded !mb-4">
                        <strong class="text-yellow-800 break-all text-sm">{{ $pendingDomain->domain_name }}</strong>
                    </div>

                    <div>
                        <label for="rejectNotes"
                            class="text-sm flex items-center !mb-2 after:content-['*'] after:mt-1 after:ml-1 after:text-red-500">
                            Reason for Rejection
                        </label>
                        <textarea
                            class="bg-gray-50 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)] resize-y min-h-[96px]"
                            id="rejectNotes" name="notes" rows="4" required
                            placeholder="e.g., Invalid API key, domain not accessible, duplicate submission..."></textarea>
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 justify-end !px-6 !py-4 border-t border-gray-100 bg-gray-50">
                    <button type="button"
                        class="!px-4 !py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded transition-all text-sm font-medium"
                        onclick="closeRejectModal()">
                        Cancel
                    </button>
                    <button type="submit"
                        class="!px-4 !py-2.5 bg-red-600 hover:bg-red-700 text-white rounded transition-all text-sm font-medium">
                        Reject Domain
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Copy API key - Pure vanilla JavaScript
        function copyApiKey(apiKey, button) {
            const originalText = button.textContent;

            // Create temporary textarea
            const textarea = document.createElement('textarea');
            textarea.value = apiKey;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.top = '0';
            document.body.appendChild(textarea);

            // Select and copy
            textarea.focus();
            textarea.select();

            let success = false;
            try {
                success = document.execCommand('copy');
            } catch (err) {
                console.error('Copy failed:', err);
            }

            // Remove textarea
            document.body.removeChild(textarea);

            // Show feedback
            if (success) {
                button.textContent = 'Copied!';
                button.classList.add('copy-success');

                setTimeout(function() {
                    button.textContent = originalText;
                    button.classList.remove('copy-success');
                }, 2000);
            } else {
                alert('Failed to copy. API Key: ' + apiKey);
            }
        }

        // Modal functions
        function showRejectModal() {
            const modal = document.getElementById('rejectModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeRejectModal() {
            const modal = document.getElementById('rejectModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('rejectNotes').value = '';
            document.body.classList.remove('overflow-hidden');
        }

        document.getElementById('rejectModal')?.addEventListener('click', function (e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeRejectModal();
            }
        });
    </script>
@endpush
