@extends('admin.layout.layout')

@push('style')
    <style>
        .domain-url {
            word-break: break-all;
            max-width: 300px;
        }

        @media (min-width: 768px) {
            .domain-url {
                max-width: 280px;
            }
        }

        .pending-domain-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: #fff;
        }

        .pending-domain-card.is-unviewed {
            background: #fff7ed;
            border-color: #fed7aa;
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

        .page-header-btn-primary {
            background-color: var(--primary-color);
            color: #fff;
        }

        .page-header-btn-primary:hover {
            background-color: #e0410f;
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

        .pending-toolbar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 40px;
            padding: 0.5rem 0.875rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.375rem;
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        .pending-toolbar-btn-primary {
            background-color: var(--primary-color);
            color: #fff;
        }

        .pending-toolbar-btn-primary:hover {
            background-color: #e0410f;
        }

        .pending-toolbar-btn-muted {
            background-color: #fff;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .pending-toolbar-btn-muted:hover {
            background-color: var(--primary-color);
            color: #fff;
        }

        .theme-badge {
            background-color: var(--primary-color);
            color: #fff;
        }

        .theme-badge-soft {
            background-color: rgba(255, 74, 23, 0.12);
            color: #c2410c;
        }

        .campaign-list-table tbody tr.bg-orange-50 td.actions-col {
            background: #fff7ed;
        }

        .campaign-list-table tbody tr.bg-orange-50:hover td.actions-col {
            background: #ffedd5;
        }
    </style>
    @include('admin.campaigns.partials.campaign-list-table-styles')
@endpush

@section('title', 'Pending Domains')

@section('main-content')
    {{-- Breadcrumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="w-full md:flex-1 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">
                    Pending Domains
                    @if ($unviewedCount > 0)
                        <span class="!ml-2 !px-2 !py-1 text-xs font-semibold theme-badge rounded-full">{{ $unviewedCount }} new</span>
                    @endif
                </h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Pending Domains</a>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full md:w-auto md:shrink-0">
                @if ($pendingCount > 0)
                    <a href="{{ route('admin.transfer-domains.step1') }}"
                        class="page-header-btn page-header-btn-primary w-full">
                        <span class="material-symbols-outlined !text-base">swap_horiz</span>
                        <span>Transfer Domains ({{ $pendingCount }})</span>
                    </a>
                @endif
                <a href="{{ route('admin.webhook-secrets.index') }}"
                    class="page-header-btn page-header-btn-secondary w-full {{ $pendingCount > 0 ? '' : 'sm:col-span-2' }}">
                    <span class="material-symbols-outlined !text-base">key</span>
                    <span>Manage Webhook Secrets</span>
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

    {{-- Filters --}}
    <div class="w-full content-card !mt-4">
        <form method="GET" action="{{ route('admin.pending-domains.index') }}"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-3 lg:gap-4 items-end">
            <div class="w-full flex flex-col gap-1.5">
                <label for="status" class="text-sm font-medium text-gray-700">Status</label>
                <select class="bg-gray-50 border border-gray-200 !px-3 !py-2.5 text-sm w-full rounded-md outline-none focus:border-[var(--primary-color)]"
                    id="status" name="status" onchange="this.form.submit()">
                    <option value="">Pending Only (Default)</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="w-full flex flex-col gap-1.5">
                <label for="viewed" class="text-sm font-medium text-gray-700">Viewed Status</label>
                <select class="bg-gray-50 border border-gray-200 !px-3 !py-2.5 text-sm w-full rounded-md outline-none focus:border-[var(--primary-color)]"
                    id="viewed" name="viewed" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="0" {{ request('viewed') === '0' ? 'selected' : '' }}>Unviewed Only</option>
                    <option value="1" {{ request('viewed') === '1' ? 'selected' : '' }}>Viewed Only</option>
                </select>
            </div>
            <div class="w-full sm:col-span-2 lg:col-span-1">
                <a href="{{ route('admin.pending-domains.index') }}"
                    class="flex !px-3 !py-2.5 text-sm font-medium justify-center transition-colors bg-white hover:bg-gray-50 text-gray-700 rounded-md cursor-pointer w-full border border-gray-200">
                    Reset Filters
                </a>
            </div>
        </form>
    </div>

    {{-- Pending Domains List --}}
    <div class="w-full content-card min-w-0 !mt-4">
        @if ($pendingDomains->count() > 0)
            <div class="flex flex-col gap-4 !mb-4">
                <form id="bulkActionForm" method="POST" class="w-full">
                    @csrf
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full sm:w-auto sm:min-w-[22rem]">
                            <button type="button" class="pending-toolbar-btn pending-toolbar-btn-primary w-full" onclick="bulkTransfer()">
                                <span class="material-symbols-outlined !text-base">swap_horiz</span>
                                <span>Transfer Selected</span>
                            </button>
                            <button type="button" class="pending-toolbar-btn pending-toolbar-btn-muted w-full" onclick="bulkReject()">
                                <span class="material-symbols-outlined !text-base">block</span>
                                <span>Reject Selected</span>
                            </button>
                        </div>
                        <span class="text-sm text-gray-500 sm:text-right" id="selectedCount">0 selected</span>
                    </div>
                </form>

                <div class="w-full">
                    <label for="pendingDomainSearch" class="sr-only">Search domains</label>
                    <input type="search" id="pendingDomainSearch" placeholder="Search domain, ID, or webhook secret..."
                        class="bg-gray-50 border border-gray-200 !px-3 !py-2.5 text-sm w-full rounded-md outline-none focus:border-[var(--primary-color)]">
                </div>
            </div>

            {{-- Mobile card list --}}
            <div class="md:hidden flex flex-col gap-3" id="pendingDomainsMobileList">
                @foreach ($pendingDomains as $domain)
                    <article
                        class="pending-domain-card pending-domain-item {{ !$domain->viewed ? 'is-unviewed' : '' }} !p-4"
                        data-search="{{ strtolower($domain->id.' '.$domain->domain_name.' '.($domain->webhookSecret?->name ?? '').' '.$domain->status) }}">
                        <div class="flex items-start gap-3 !mb-3">
                            @if ($domain->status === 'pending')
                                <input type="checkbox" class="domain-checkbox rounded mt-1 shrink-0" form="bulkActionForm"
                                    name="domain_ids[]" value="{{ $domain->id }}">
                            @else
                                <span class="w-4 shrink-0"></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2 !mb-1">
                                    <span class="text-xs text-gray-500">#{{ $domain->id }}</span>
                                    @if ($domain->status === 'pending')
                                        <span class="!px-2 !py-0.5 text-xs font-semibold rounded-full theme-badge-soft">Pending</span>
                                    @elseif($domain->status === 'approved')
                                        <span class="!px-2 !py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                    @else
                                        <span class="!px-2 !py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                                    @endif
                                    @if ($domain->viewed)
                                        <span class="!px-2 !py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Viewed</span>
                                    @else
                                        <span class="!px-2 !py-0.5 text-xs font-semibold rounded-full theme-badge-soft">New</span>
                                    @endif
                                </div>
                                <a href="{{ $domain->domain_url }}" target="_blank" rel="noopener"
                                    class="text-base font-semibold text-blue-600 hover:text-blue-800 break-all">
                                    {{ $domain->domain_name }}
                                </a>
                            </div>
                        </div>
                        <dl class="grid grid-cols-1 gap-2 text-sm text-gray-600 !mb-3 !pl-7">
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="font-medium text-gray-700 shrink-0">Webhook:</dt>
                                <dd class="break-all">{{ $domain->webhookSecret?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex flex-wrap gap-x-2">
                                <dt class="font-medium text-gray-700 shrink-0">Submitted:</dt>
                                <dd>{{ $domain->created_at->diffForHumans() }}</dd>
                            </div>
                        </dl>
                        <div class="!pl-7">
                            @include('admin.domains.pending.partials.domain-row-actions', ['domain' => $domain])
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop table --}}
            <div class="hidden md:block overflow-x-auto w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                <table id="pendingDomainsTable" class="campaign-list-table w-full min-w-[900px] text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-800 text-white">
                        <tr>
                            <th class="!px-3 sm:!px-6 !py-3">
                                <input type="checkbox" id="selectAll" class="rounded" form="bulkActionForm">
                            </th>
                                <th class="!px-3 sm:!px-6 !py-3">ID</th>
                                <th class="!px-3 sm:!px-6 !py-3">Domain Name</th>
                                <th class="!px-3 sm:!px-6 !py-3">Status</th>
                                <th class="!px-3 sm:!px-6 !py-3">Viewed</th>
                                <th class="!px-3 sm:!px-6 !py-3">Webhook Secret</th>
                                <th class="!px-3 sm:!px-6 !py-3">Submitted</th>
                                <th class="actions-col min-w-[200px] !px-3 sm:!px-6 !py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendingDomains as $domain)
                                <tr class="pending-domain-item {{ !$domain->viewed ? 'bg-orange-50' : 'bg-white' }} border-b hover:bg-gray-50"
                                    data-search="{{ strtolower($domain->id.' '.$domain->domain_name.' '.($domain->webhookSecret?->name ?? '').' '.$domain->status) }}">
                                    <td class="!px-3 sm:!px-6 !py-4">
                                        @if ($domain->status === 'pending')
                                            <input type="checkbox" class="domain-checkbox rounded" form="bulkActionForm" name="domain_ids[]" value="{{ $domain->id }}">
                                        @endif
                                    </td>
                                    <td class="!px-3 sm:!px-6 !py-4">{{ $domain->id }}</td>
                                    <td class="!px-3 sm:!px-6 !py-4 domain-url">{{ $domain->domain_name }}</td>
                                    <td class="!px-3 sm:!px-6 !py-4">
                                        @if ($domain->status === 'pending')
                                            <span class="!px-2 !py-1 text-xs font-semibold rounded-full theme-badge-soft">Pending</span>
                                        @elseif($domain->status === 'approved')
                                            <span class="!px-2 !py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                        @else
                                            <span class="!px-2 !py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                                        @endif
                                    </td>
                                    <td class="!px-3 sm:!px-6 !py-4">
                                        @if ($domain->viewed)
                                            <span class="!px-2 !py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Viewed</span>
                                        @else
                                            <span class="!px-2 !py-1 text-xs font-semibold rounded-full theme-badge-soft">New</span>
                                        @endif
                                    </td>
                                    <td class="!px-3 sm:!px-6 !py-4">{{ $domain->webhookSecret?->name ?? '—' }}</td>
                                    <td class="!px-3 sm:!px-6 !py-4">{{ $domain->created_at->diffForHumans() }}</td>
                                    <td class="actions-col !px-3 sm:!px-6 !py-4">
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
            <div class="!p-4 bg-blue-50 border border-blue-200 rounded text-blue-700">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <span>No pending domains found. Domains submitted via webhook will appear here.</span>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('popup')
    {{-- Reject Modal --}}
    <div id="rejectModal"
        class="fixed inset-0 z-[1100] hidden items-center justify-center p-4 bg-black/50"
        role="dialog" aria-modal="true">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg mx-auto overflow-hidden">
            <form id="rejectForm" method="POST">
                @csrf
                <div class="!p-6">
                    <div class="flex items-start gap-3 !mb-4">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-red-600">block</span>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900">Reject Domain</h3>
                            <p class="text-sm text-gray-500">Reject domain: <strong id="rejectDomainName" class="break-all"></strong></p>
                        </div>
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

    {{-- Bulk Reject Modal --}}
    <div id="bulkRejectModal"
        class="fixed inset-0 z-[1100] hidden items-center justify-center p-4 bg-black/50"
        role="dialog" aria-modal="true">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg mx-auto overflow-hidden">
            <form id="bulkRejectForm" method="POST" action="{{ route('admin.pending-domains.bulk.reject') }}">
                @csrf
                <div class="!p-6">
                    <div class="flex items-start gap-3 !mb-4">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-red-600">block</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Bulk Reject Domains</h3>
                            <p class="text-sm text-gray-500">Reject <strong id="bulkRejectCount"></strong> selected domain(s)</p>
                        </div>
                    </div>
                    <div>
                        <label for="bulkRejectNotes"
                            class="text-sm flex items-center !mb-2 after:content-['*'] after:mt-1 after:ml-1 after:text-red-500">
                            Reason for Rejection
                        </label>
                        <textarea
                            class="bg-gray-50 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)] resize-y min-h-[96px]"
                            id="bulkRejectNotes" name="notes" rows="4" required
                            placeholder="e.g., Invalid API key, domain not accessible, duplicate submission..."></textarea>
                    </div>
                    <div id="bulkRejectDomainIds"></div>
                </div>
                <div class="flex flex-col-reverse sm:flex-row gap-3 justify-end !px-6 !py-4 border-t border-gray-100 bg-gray-50">
                    <button type="button"
                        class="!px-4 !py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded transition-all text-sm font-medium"
                        onclick="closeBulkRejectModal()">
                        Cancel
                    </button>
                    <button type="submit"
                        class="!px-4 !py-2.5 bg-red-600 hover:bg-red-700 text-white rounded transition-all text-sm font-medium">
                        Reject Selected
                    </button>
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

        function showRejectModal(id, domainName) {
            document.getElementById('rejectDomainName').textContent = domainName;
            document.getElementById('rejectForm').setAttribute('action', '/admin/pending-domains/' + id + '/reject');
            openModal('rejectModal');
        }

        function closeRejectModal() {
            closeModal('rejectModal');
            const notes = document.getElementById('rejectNotes');
            if (notes) {
                notes.value = '';
            }
        }

        function closeBulkRejectModal() {
            closeModal('bulkRejectModal');
            const notes = document.getElementById('bulkRejectNotes');
            if (notes) {
                notes.value = '';
            }
        }

        ['rejectModal', 'bulkRejectModal'].forEach(function (modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    if (modalId === 'rejectModal') {
                        closeRejectModal();
                    } else {
                        closeBulkRejectModal();
                    }
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeRejectModal();
                closeBulkRejectModal();
            }
        });

        function bulkTransfer() {
            const checkedBoxes = document.querySelectorAll('.domain-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one domain');
                return;
            }

            const form = document.getElementById('bulkActionForm');
            form.setAttribute('action', '{{ route('admin.transfer-domains.step2') }}');
            form.setAttribute('method', 'POST');
            form.submit();
        }

        function bulkReject() {
            const checkedBoxes = document.querySelectorAll('.domain-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one domain');
                return;
            }

            document.getElementById('bulkRejectCount').textContent = checkedBoxes.length;
            const container = document.getElementById('bulkRejectDomainIds');
            container.innerHTML = '';

            checkedBoxes.forEach(function (cb) {
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
