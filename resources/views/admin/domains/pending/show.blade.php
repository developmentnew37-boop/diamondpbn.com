@extends('admin.layout.layout')

@push('style')
    @include('admin.domains.pending.partials.styles')
@endpush

@section('title', 'Pending Domain #' . $pendingDomain->id)

@section('main-content')
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <h2 class="page-title">Pending Domain Details</h2>
                <div class="breadcrumb flex-wrap !mt-1">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.pending-domains.index') }}" class="breadcrumb-link">Pending Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">#{{ $pendingDomain->id }}</span>
                    </div>
                </div>
            </div>
            <div class="pd-header-actions shrink-0">
                <a href="{{ route('admin.pending-domains.index') }}" class="pd-btn pd-btn-outline">
                    <span class="material-symbols-outlined !text-base">arrow_back</span>
                    Back to list
                </a>
            </div>
        </div>
    </div>

    @if (session('success') || session('error'))
        <div class="w-full flex flex-col gap-2 !mt-4">
            @if (session('success'))
                <div class="!p-4 text-sm rounded-lg bg-green-50 border border-green-200 text-green-800" role="alert">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="!p-4 text-sm rounded-lg bg-red-50 border border-red-200 text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif
        </div>
    @endif

    <div class="pd-detail-grid w-full !mt-4">
        {{-- Main info --}}
        <div class="pd-detail-card is-primary">
            <div class="pd-detail-hero">
                <div class="min-w-0">
                    <a href="{{ $pendingDomain->domain_url }}" target="_blank" rel="noopener" class="pd-domain-link pd-detail-hero-domain">
                        {{ $pendingDomain->domain_name }}
                        <span class="material-symbols-outlined">open_in_new</span>
                    </a>
                    <div class="pd-detail-meta-row">
                        <span class="pd-id">Record #{{ $pendingDomain->id }}</span>
                        @if ($inInventory)
                            <span class="pd-badge pd-badge-inventory">In inventory</span>
                        @else
                            <span class="pd-badge pd-badge-new-site">New site</span>
                        @endif
                        @if ($pendingDomain->status === 'pending')
                            <span class="pd-badge pd-badge-pending">Pending</span>
                        @elseif($pendingDomain->status === 'approved')
                            <span class="pd-badge pd-badge-approved">Approved</span>
                        @else
                            <span class="pd-badge pd-badge-rejected">Rejected</span>
                        @endif
                    </div>
                </div>
            </div>

            <p class="pd-detail-card-title">Domain information</p>

            <dl class="pd-detail-dl">
                <div class="pd-detail-row">
                    <dt class="pd-detail-dt">API key</dt>
                    <dd class="pd-detail-dd">
                        <div class="pd-api-key-box">
                            <code class="pd-api-key-value" id="pendingApiKey">{{ $pendingDomain->api_key }}</code>
                            <button type="button" class="pd-copy-btn" data-api-key="{{ $pendingDomain->api_key }}" onclick="copyApiKey(this)">
                                <span class="material-symbols-outlined !text-base">content_copy</span>
                                Copy
                            </button>
                        </div>
                    </dd>
                </div>

                <div class="pd-detail-row">
                    <dt class="pd-detail-dt">Viewed</dt>
                    <dd class="pd-detail-dd">
                        @if ($pendingDomain->viewed)
                            <span class="pd-badge pd-badge-viewed">Viewed</span>
                        @else
                            <span class="pd-badge pd-badge-new">Unread</span>
                        @endif
                    </dd>
                </div>

                <div class="pd-detail-row">
                    <dt class="pd-detail-dt">Webhook secret</dt>
                    <dd class="pd-detail-dd">
                        @if ($pendingDomain->webhookSecret)
                            <a href="{{ route('admin.webhook-secrets.edit', $pendingDomain->webhookSecret->id) }}"
                                class="text-[var(--primary-color)] hover:underline font-medium">
                                {{ $pendingDomain->webhookSecret->name }}
                            </a>
                            <span class="pd-badge {{ $pendingDomain->webhookSecret->is_active ? 'pd-badge-approved' : 'pd-badge-viewed' }} !ml-2">
                                {{ $pendingDomain->webhookSecret->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        @else
                            <span class="pd-meta">—</span>
                        @endif
                    </dd>
                </div>

                <div class="pd-detail-row">
                    <dt class="pd-detail-dt">Submitted</dt>
                    <dd class="pd-detail-dd">
                        {{ $pendingDomain->created_at->format('M j, Y · H:i') }}
                        <span class="pd-meta">({{ $pendingDomain->created_at->diffForHumans() }})</span>
                    </dd>
                </div>

                @if ($pendingDomain->approved_at)
                    <div class="pd-detail-row">
                        <dt class="pd-detail-dt">Approved</dt>
                        <dd class="pd-detail-dd">{{ $pendingDomain->approved_at->format('M j, Y · H:i') }}</dd>
                    </div>
                @endif

                @if ($pendingDomain->rejected_at)
                    <div class="pd-detail-row">
                        <dt class="pd-detail-dt">Rejected</dt>
                        <dd class="pd-detail-dd">{{ $pendingDomain->rejected_at->format('M j, Y · H:i') }}</dd>
                    </div>
                @endif

                @if ($pendingDomain->notes)
                    <div class="pd-detail-row">
                        <dt class="pd-detail-dt">Notes</dt>
                        <dd class="pd-detail-dd">
                            <div class="pd-guide-block !bg-white">{{ $pendingDomain->notes }}</div>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Sidebar --}}
        <div class="flex flex-col gap-4">
            @if ($pendingDomain->status === 'pending')
                <div class="pd-detail-card">
                    <p class="pd-detail-card-title">Actions</p>
                    <div class="pd-action-stack">
                        @if ($inInventory)
                            <form method="POST" action="{{ route('admin.pending-domains.sync-inventory', $pendingDomain->id) }}"
                                onsubmit="return confirm('Sync API key for this domain and remove from pending?')">
                                @csrf
                                <button type="submit" class="pd-btn pd-btn-outline is-brand w-full">
                                    <span class="material-symbols-outlined !text-base">sync</span>
                                    Sync API key
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('admin.transfer-domains.step2', ['domain_ids' => [$pendingDomain->id]]) }}"
                            class="pd-btn pd-btn-success">
                            <span class="material-symbols-outlined !text-base">swap_horiz</span>
                            Transfer to domains
                        </a>

                        <button type="button" class="pd-btn pd-btn-danger" onclick="showRejectModal()">
                            <span class="material-symbols-outlined !text-base">block</span>
                            Reject domain
                        </button>

                        <form action="{{ route('admin.pending-domains.destroy', $pendingDomain->id) }}" method="POST"
                            onsubmit="return confirm('Delete this pending record permanently?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="pd-btn pd-btn-outline w-full !text-red-600 !border-red-200 hover:!bg-red-50">
                                <span class="material-symbols-outlined !text-base">delete</span>
                                Delete record
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="pd-detail-card">
                <p class="pd-detail-card-title">What happens next</p>

                @if ($pendingDomain->status === 'pending')
                    @if ($inInventory)
                        <div class="pd-guide-block">
                            <p class="pd-guide-title">
                                <span class="material-symbols-outlined">sync</span>
                                Already in inventory
                            </p>
                            <ul class="pd-guide-list">
                                <li>Use <strong>Sync API key</strong> to update the key only — category and metrics stay the same.</li>
                                <li>Or use <strong>Transfer</strong> if you also want to assign a category (overwrites category).</li>
                            </ul>
                        </div>
                    @else
                        <div class="pd-guide-block">
                            <p class="pd-guide-title">
                                <span class="material-symbols-outlined">add_link</span>
                                New site
                            </p>
                            <ul class="pd-guide-list">
                                <li>Use <strong>Transfer to domains</strong> and pick a category.</li>
                                <li>Plugin connection is verified in the background after transfer.</li>
                            </ul>
                        </div>
                    @endif

                    <div class="pd-guide-block">
                        <p class="pd-guide-title">
                            <span class="material-symbols-outlined">block</span>
                            If you reject
                        </p>
                        <ul class="pd-guide-list">
                            <li>Domain stays out of your inventory.</li>
                            <li>Record is kept for audit with your rejection reason.</li>
                        </ul>
                    </div>
                @elseif($pendingDomain->status === 'approved')
                    <div class="pd-status-banner is-success">
                        This submission was approved and processed. The domain should appear in your inventory with the updated API key or category.
                    </div>
                @else
                    <div class="pd-status-banner is-error">
                        This submission was rejected and will not be added to your domains list.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('popup')
    <div id="rejectModal"
        class="fixed inset-0 z-[1100] hidden items-center justify-center p-4 bg-black/50"
        role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto overflow-hidden">
            <form action="{{ route('admin.pending-domains.reject', $pendingDomain->id) }}" method="POST">
                @csrf
                <div class="!p-6">
                    <h3 id="rejectModalTitle" class="text-lg font-semibold text-gray-900 !mb-1">Reject domain</h3>
                    <p class="text-sm text-gray-500 !mb-4 break-all">{{ $pendingDomain->domain_name }}</p>
                    <label for="rejectNotes" class="pd-field-label">Reason <span class="text-red-500">*</span></label>
                    <textarea class="pd-select !min-h-[96px] resize-y" id="rejectNotes" name="notes" rows="4" required
                        placeholder="Invalid API key, duplicate, site unreachable…"></textarea>
                </div>
                <div class="flex flex-col-reverse sm:flex-row gap-3 justify-end !px-6 !py-4 border-t border-gray-100 bg-gray-50">
                    <button type="button" class="pd-btn pd-btn-outline" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="pd-btn pd-btn-primary !bg-red-600 hover:!bg-red-700">Reject</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function copyApiKey(button) {
            const apiKey = button.getAttribute('data-api-key') || '';
            const originalHtml = button.innerHTML;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(apiKey).then(function () {
                    showCopySuccess(button, originalHtml);
                }).catch(function () {
                    fallbackCopy(apiKey, button, originalHtml);
                });
                return;
            }

            fallbackCopy(apiKey, button, originalHtml);
        }

        function fallbackCopy(apiKey, button, originalHtml) {
            const textarea = document.createElement('textarea');
            textarea.value = apiKey;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            let success = false;
            try {
                success = document.execCommand('copy');
            } catch (e) {}
            document.body.removeChild(textarea);
            if (success) {
                showCopySuccess(button, originalHtml);
            }
        }

        function showCopySuccess(button, originalHtml) {
            button.innerHTML = '<span class="material-symbols-outlined !text-base">check</span> Copied';
            button.classList.add('is-success');
            setTimeout(function () {
                button.innerHTML = originalHtml;
                button.classList.remove('is-success');
            }, 2000);
        }

        function showRejectModal() {
            document.getElementById('rejectModal').classList.remove('hidden');
            document.getElementById('rejectModal').classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
            document.getElementById('rejectModal').classList.remove('flex');
            document.getElementById('rejectNotes').value = '';
            document.body.classList.remove('overflow-hidden');
        }

        document.getElementById('rejectModal')?.addEventListener('click', function (e) {
            if (e.target === this) closeRejectModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeRejectModal();
        });
    </script>
@endpush
