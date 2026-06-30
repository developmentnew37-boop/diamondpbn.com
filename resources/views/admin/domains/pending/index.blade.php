@extends('admin.layout.layout')

@push('style')
    @include('admin.domains.pending.partials.styles')
@endpush

@section('title', 'Pending Domains')

@section('main-content')
    {{-- Page header --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <h2 class="page-title flex flex-wrap items-center gap-2">
                    Pending Domains
                    @if ($unviewedCount > 0)
                        <span class="pd-badge pd-badge-new">{{ $unviewedCount }} unread</span>
                    @endif
                </h2>
                <div class="breadcrumb flex-wrap !mt-1">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Domains</span>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Pending Domains</span>
                    </div>
                </div>
                <p class="text-sm text-gray-500 !mt-2 max-w-2xl">
                    Review webhook submissions from remote WordPress sites. Sync API keys for domains already in inventory, or transfer new sites into a category.
                </p>
            </div>
            <div class="pd-header-actions w-full lg:w-auto shrink-0">
                @if ($pendingCount > 0)
                    <button type="button"
                        class="pd-btn pd-btn-outline is-brand {{ $existingInventoryCount === 0 ? 'is-disabled' : '' }}"
                        @if ($existingInventoryCount > 0)
                            onclick="openSyncExistingModal()"
                        @else
                            onclick="openSyncUnavailableModal()"
                        @endif>
                        <span class="material-symbols-outlined !text-base">sync</span>
                        Sync Existing
                        @if ($existingInventoryCount > 0)
                            <span class="!px-1.5 !py-0.5 text-xs rounded-full bg-orange-100 text-orange-800">{{ $existingInventoryCount }}</span>
                        @endif
                    </button>
                    <a href="{{ route('admin.transfer-domains.step1') }}" class="pd-btn pd-btn-primary">
                        <span class="material-symbols-outlined !text-base">swap_horiz</span>
                        Transfer
                        <span class="!px-1.5 !py-0.5 text-xs rounded-full bg-white/20">{{ $pendingCount }}</span>
                    </a>
                @endif
                <a href="{{ route('admin.webhook-secrets.index') }}" class="pd-btn pd-btn-outline">
                    <span class="material-symbols-outlined !text-base">key</span>
                    Webhook Secrets
                </a>
            </div>
        </div>
    </div>

    {{-- Alerts --}}
    @if (session('success') || session('error') || session('transfer_errors'))
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
            @if (session('transfer_errors'))
                <div class="!p-4 text-sm rounded-lg bg-amber-50 border border-amber-200 text-amber-900" role="alert">
                    <p class="font-semibold !mb-2">Some domains could not be processed</p>
                    <ul class="list-disc !pl-5 space-y-1">
                        @foreach (session('transfer_errors') as $transferError)
                            <li>{{ $transferError }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    {{-- Stats --}}
    <div class="pd-stat-grid w-full !mt-4">
        <div class="pd-stat-card is-accent">
            <span class="pd-stat-label">Pending</span>
            <span class="pd-stat-value">{{ number_format($pendingCount) }}</span>
            <span class="pd-stat-hint">Awaiting review</span>
        </div>
        <div class="pd-stat-card">
            <span class="pd-stat-label">Unread</span>
            <span class="pd-stat-value">{{ number_format($unviewedCount) }}</span>
            <span class="pd-stat-hint">Not opened yet</span>
        </div>
        <div class="pd-stat-card">
            <span class="pd-stat-label">In inventory</span>
            <span class="pd-stat-value">{{ number_format($existingInventoryCount) }}</span>
            <span class="pd-stat-hint">Ready to sync API key</span>
        </div>
        <div class="pd-stat-card">
            <span class="pd-stat-label">New sites</span>
            <span class="pd-stat-value">{{ number_format($newSitesCount) }}</span>
            <span class="pd-stat-hint">Need transfer + category</span>
        </div>
    </div>

    @if ($pendingCount > 0 && $existingInventoryCount === 0)
        <div class="pd-callout !mt-4">
            <strong>Sync Existing</strong> is unavailable because none of these hostnames exist in
            <em>Domains List</em> yet. Use <strong>Transfer</strong> to add new sites, or add the domain manually first — then webhook resubmissions can be synced.
        </div>
    @endif

    {{-- Filters --}}
    <div class="w-full content-card !mt-4">
        <form method="GET" action="{{ route('admin.pending-domains.index') }}" class="pd-filter-grid">
            <div>
                <label for="status" class="pd-field-label">Status</label>
                <select class="pd-select" id="status" name="status" onchange="this.form.submit()">
                    <option value="">Pending only (default)</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div>
                <label for="viewed" class="pd-field-label">Viewed</label>
                <select class="pd-select" id="viewed" name="viewed" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="0" {{ request('viewed') === '0' ? 'selected' : '' }}>Unread only</option>
                    <option value="1" {{ request('viewed') === '1' ? 'selected' : '' }}>Viewed only</option>
                </select>
            </div>
            <div class="flex items-end">
                <a href="{{ route('admin.pending-domains.index') }}" class="pd-btn pd-btn-outline w-full">
                    Reset filters
                </a>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="w-full content-card min-w-0 !mt-4">
        @if ($pendingDomains->count() > 0)
            <form id="bulkActionForm" method="POST">
                @csrf
                <div class="pd-toolbar">
                    <div class="pd-toolbar-actions">
                        <button type="button" class="pd-btn pd-btn-primary pd-btn-sm" onclick="bulkTransfer()">
                            <span class="material-symbols-outlined !text-base">swap_horiz</span>
                            Transfer selected
                        </button>
                        <button type="button" class="pd-btn pd-btn-outline pd-btn-sm" onclick="bulkReject()">
                            <span class="material-symbols-outlined !text-base">block</span>
                            Reject selected
                        </button>
                        <span class="pd-selected-count" id="selectedCount">0 selected</span>
                    </div>
                    <div class="pd-search-wrap">
                        <span class="material-symbols-outlined">search</span>
                        <label for="pendingDomainSearch" class="sr-only">Search domains</label>
                        <input type="search" id="pendingDomainSearch" class="pd-search-input"
                            placeholder="Search domain, ID, webhook…">
                    </div>
                </div>
            </form>

            {{-- Mobile --}}
            <div class="md:hidden flex flex-col gap-3" id="pendingDomainsMobileList">
                @foreach ($pendingDomains as $domain)
                    <article class="pd-mobile-card pending-domain-item {{ !$domain->viewed ? 'is-unviewed' : '' }}"
                        data-search="{{ strtolower($domain->id.' '.$domain->domain_name.' '.($domain->webhookSecret?->name ?? '').' '.$domain->status) }}">
                        <div class="flex items-start gap-3 !mb-3">
                            @if ($domain->status === 'pending')
                                <input type="checkbox" class="domain-checkbox rounded mt-1 shrink-0" form="bulkActionForm"
                                    name="domain_ids[]" value="{{ $domain->id }}">
                            @else
                                <span class="w-4 shrink-0"></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 !mb-2">
                                    <span class="pd-id">#{{ $domain->id }}</span>
                                    @include('admin.domains.pending.partials.badges', ['domain' => $domain])
                                </div>
                                <a href="{{ $domain->domain_url }}" target="_blank" rel="noopener" class="pd-domain-link">
                                    {{ $domain->domain_name }}
                                    <span class="material-symbols-outlined">open_in_new</span>
                                </a>
                            </div>
                        </div>
                        <dl class="grid grid-cols-1 gap-1.5 text-sm !mb-3 !pl-7 pd-meta">
                            <div><span class="font-medium text-gray-700">Webhook:</span> {{ $domain->webhookSecret?->name ?? '—' }}</div>
                            <div><span class="font-medium text-gray-700">Submitted:</span> {{ $domain->created_at->diffForHumans() }}</div>
                        </dl>
                        <div class="!pl-7">
                            @include('admin.domains.pending.partials.domain-row-actions', ['domain' => $domain])
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop --}}
            <div class="hidden md:block pd-table-wrap">
                <table id="pendingDomainsTable" class="pd-table">
                    <thead>
                        <tr>
                            <th class="w-10">
                                <input type="checkbox" id="selectAll" class="rounded" form="bulkActionForm" aria-label="Select all">
                            </th>
                            <th>ID</th>
                            <th>Domain</th>
                            <th>Inventory</th>
                            <th>Status</th>
                            <th>Viewed</th>
                            <th>Webhook</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingDomains as $domain)
                            <tr class="pending-domain-item {{ !$domain->viewed ? 'is-unviewed' : '' }}"
                                data-search="{{ strtolower($domain->id.' '.$domain->domain_name.' '.($domain->webhookSecret?->name ?? '').' '.$domain->status) }}">
                                <td>
                                    @if ($domain->status === 'pending')
                                        <input type="checkbox" class="domain-checkbox rounded" form="bulkActionForm"
                                            name="domain_ids[]" value="{{ $domain->id }}">
                                    @endif
                                </td>
                                <td><span class="pd-id">{{ $domain->id }}</span></td>
                                <td>
                                    <a href="{{ $domain->domain_url }}" target="_blank" rel="noopener" class="pd-domain-link">
                                        {{ $domain->domain_name }}
                                        <span class="material-symbols-outlined">open_in_new</span>
                                    </a>
                                </td>
                                <td>
                                    @if (isset($existingInventoryNames[$domain->normalized_name]))
                                        <span class="pd-badge pd-badge-inventory">In inventory</span>
                                    @else
                                        <span class="pd-badge pd-badge-new-site">New site</span>
                                    @endif
                                </td>
                                <td>@include('admin.domains.pending.partials.badges', ['domain' => $domain, 'statusOnly' => true])</td>
                                <td>
                                    @if ($domain->viewed)
                                        <span class="pd-badge pd-badge-viewed">Viewed</span>
                                    @else
                                        <span class="pd-badge pd-badge-new">Unread</span>
                                    @endif
                                </td>
                                <td class="pd-meta">{{ $domain->webhookSecret?->name ?? '—' }}</td>
                                <td class="pd-meta">{{ $domain->created_at->diffForHumans() }}</td>
                                <td>
                                    @include('admin.domains.pending.partials.domain-row-actions', ['domain' => $domain])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="!mt-4">
                {{ $pendingDomains->links() }}
            </div>
        @else
            <div class="pd-empty">
                <div class="pd-empty-icon">
                    <span class="material-symbols-outlined !text-2xl">inbox</span>
                </div>
                <p class="font-medium text-gray-700 !mb-1">No pending domains</p>
                <p class="text-sm">Domains submitted via webhook will appear here for review.</p>
            </div>
        @endif
    </div>
@endsection

@section('popup')
    @if ($existingInventoryCount > 0)
        <div id="syncExistingModal"
            class="fixed inset-0 z-[1100] hidden items-center justify-center p-4 bg-black/50"
            role="dialog" aria-modal="true">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto overflow-hidden">
                <form method="POST" action="{{ route('admin.pending-domains.sync-existing') }}">
                    @csrf
                    <div class="!p-6">
                        <div class="flex items-start gap-3 !mb-4">
                            <div class="w-11 h-11 bg-orange-100 rounded-full flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[var(--primary-color)]">sync</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Sync existing domains</h3>
                                <p class="text-sm text-gray-600 !mt-1">
                                    Update API keys for <strong>{{ $existingInventoryCount }}</strong> domain(s) already in inventory.
                                </p>
                            </div>
                        </div>
                        <ul class="text-sm text-gray-600 space-y-2 !pl-4 list-disc">
                            <li>Matches by hostname — category and metrics stay unchanged</li>
                            <li>API key updated from webhook submission</li>
                            <li>Synced rows removed from pending list</li>
                        </ul>
                    </div>
                    <div class="flex flex-col-reverse sm:flex-row gap-3 justify-end !px-6 !py-4 border-t border-gray-100 bg-gray-50">
                        <button type="button" class="pd-btn pd-btn-outline" onclick="closeSyncExistingModal()">Cancel</button>
                        <button type="submit" class="pd-btn pd-btn-primary">Sync {{ $existingInventoryCount }} domain(s)</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div id="syncUnavailableModal"
        class="fixed inset-0 z-[1100] hidden items-center justify-center p-4 bg-black/50"
        role="dialog" aria-modal="true">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-auto overflow-hidden">
            <div class="!p-6">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 bg-slate-100 rounded-full flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-slate-600">info</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Nothing to sync</h3>
                        <p class="text-sm text-gray-600 !mt-2 leading-relaxed">
                            Sync only works when the hostname already exists under <strong>Domains List</strong>.
                            These submissions are new — use <strong>Transfer</strong> to add them to inventory.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex justify-end !px-6 !py-4 border-t border-gray-100 bg-gray-50">
                <button type="button" class="pd-btn pd-btn-primary" onclick="closeSyncUnavailableModal()">Got it</button>
            </div>
        </div>
    </div>

    <div id="rejectModal"
        class="fixed inset-0 z-[1100] hidden items-center justify-center p-4 bg-black/50"
        role="dialog" aria-modal="true">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto overflow-hidden">
            <form id="rejectForm" method="POST">
                @csrf
                <div class="!p-6">
                    <h3 class="text-lg font-semibold text-gray-900 !mb-1">Reject domain</h3>
                    <p class="text-sm text-gray-500 !mb-4 break-all"><span id="rejectDomainName"></span></p>
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

    <div id="bulkRejectModal"
        class="fixed inset-0 z-[1100] hidden items-center justify-center p-4 bg-black/50"
        role="dialog" aria-modal="true">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto overflow-hidden">
            <form id="bulkRejectForm" method="POST" action="{{ route('admin.pending-domains.bulk.reject') }}">
                @csrf
                <div class="!p-6">
                    <h3 class="text-lg font-semibold text-gray-900 !mb-1">Reject selected</h3>
                    <p class="text-sm text-gray-500 !mb-4"><strong id="bulkRejectCount"></strong> domain(s)</p>
                    <label for="bulkRejectNotes" class="pd-field-label">Reason <span class="text-red-500">*</span></label>
                    <textarea class="pd-select !min-h-[96px] resize-y" id="bulkRejectNotes" name="notes" rows="4" required
                        placeholder="Reason for bulk rejection…"></textarea>
                    <div id="bulkRejectDomainIds"></div>
                </div>
                <div class="flex flex-col-reverse sm:flex-row gap-3 justify-end !px-6 !py-4 border-t border-gray-100 bg-gray-50">
                    <button type="button" class="pd-btn pd-btn-outline" onclick="closeBulkRejectModal()">Cancel</button>
                    <button type="submit" class="pd-btn pd-btn-primary !bg-red-600 hover:!bg-red-700">Reject selected</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('pendingDomainSearch');
            const items = document.querySelectorAll('.pending-domain-item');

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const query = this.value.trim().toLowerCase();
                    items.forEach(function (item) {
                        const haystack = item.getAttribute('data-search') || '';
                        item.style.display = !query || haystack.includes(query) ? '' : 'none';
                    });
                });
            }

            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    document.querySelectorAll('.domain-checkbox').forEach(function (cb) {
                        cb.checked = selectAll.checked;
                    });
                    updateSelectedCount();
                });
            }

            document.querySelectorAll('.domain-checkbox').forEach(function (cb) {
                cb.addEventListener('change', updateSelectedCount);
            });
        });

        function updateSelectedCount() {
            const count = document.querySelectorAll('.domain-checkbox:checked').length;
            const el = document.getElementById('selectedCount');
            if (el) {
                el.textContent = count + ' selected';
            }
        }

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function openSyncExistingModal() { openModal('syncExistingModal'); }
        function closeSyncExistingModal() { closeModal('syncExistingModal'); }
        function openSyncUnavailableModal() { openModal('syncUnavailableModal'); }
        function closeSyncUnavailableModal() { closeModal('syncUnavailableModal'); }

        function showRejectModal(id, domainName) {
            document.getElementById('rejectDomainName').textContent = domainName;
            document.getElementById('rejectForm').setAttribute('action', '/admin/pending-domains/' + id + '/reject');
            openModal('rejectModal');
        }

        function closeRejectModal() {
            closeModal('rejectModal');
            const notes = document.getElementById('rejectNotes');
            if (notes) notes.value = '';
        }

        function closeBulkRejectModal() {
            closeModal('bulkRejectModal');
            const notes = document.getElementById('bulkRejectNotes');
            if (notes) notes.value = '';
        }

        ['rejectModal', 'bulkRejectModal', 'syncExistingModal', 'syncUnavailableModal'].forEach(function (modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.addEventListener('click', function (e) {
                if (e.target !== modal) return;
                if (modalId === 'rejectModal') closeRejectModal();
                else if (modalId === 'bulkRejectModal') closeBulkRejectModal();
                else if (modalId === 'syncExistingModal') closeSyncExistingModal();
                else closeSyncUnavailableModal();
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            closeRejectModal();
            closeBulkRejectModal();
            closeSyncExistingModal();
            closeSyncUnavailableModal();
        });

        function bulkTransfer() {
            const checked = document.querySelectorAll('.domain-checkbox:checked');
            if (checked.length === 0) {
                alert('Select at least one domain.');
                return;
            }
            const form = document.getElementById('bulkActionForm');
            form.setAttribute('action', '{{ route('admin.transfer-domains.step2') }}');
            form.setAttribute('method', 'POST');
            form.submit();
        }

        function bulkReject() {
            const checked = document.querySelectorAll('.domain-checkbox:checked');
            if (checked.length === 0) {
                alert('Select at least one domain.');
                return;
            }
            document.getElementById('bulkRejectCount').textContent = checked.length;
            const container = document.getElementById('bulkRejectDomainIds');
            container.innerHTML = '';
            checked.forEach(function (cb) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'domain_ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            openModal('bulkRejectModal');
        }
    </script>
@endpush
