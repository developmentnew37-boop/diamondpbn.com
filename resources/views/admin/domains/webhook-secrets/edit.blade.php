@extends('admin.layout.layout')

@section('title', 'Edit Webhook Secret')

@push('style')
    <style>
        .copy-success {
            background-color: #10b981 !important;
            color: white !important;
        }
    </style>
@endpush

@section('main-content')
    {{-- Breadcrumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Edit Webhook Secret</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.webhook-secrets.index') }}" class="breadcrumb-link">Webhook Secrets</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Edit</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="w-full flex flex-col gap-4 items-center !mt-4">
        <div class="w-full max-w-[900px] content-card min-w-0">
            <div class="flex flex-col gap-5">
                <h2 class="text-xl font-semibold capitalize">Edit Webhook Secret</h2>

                <form action="{{ route('admin.webhook-secrets.update', $webhookSecret->id) }}" method="POST" class="w-full flex flex-col gap-5">
                    @csrf
                    @method('PUT')

                    <div class="w-full flex flex-col gap-3 !p-2">
                        <label for="name" class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                            Secret Name
                        </label>
                        <input type="text"
                            class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600 @error('name') border-red-500 @enderror"
                            id="name"
                            name="name"
                            value="{{ old('name', $webhookSecret->name) }}"
                            required>
                        @error('name')
                            <div class="w-full text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="w-full flex flex-col gap-3 !p-2">
                        <label for="is_active" class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                            Status
                        </label>
                        <select class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600 @error('is_active') border-red-500 @enderror"
                            id="is_active"
                            name="is_active"
                            required>
                            <option value="1" {{ old('is_active', $webhookSecret->is_active) == 1 ? 'selected' : '' }}>
                                Active
                            </option>
                            <option value="0" {{ old('is_active', $webhookSecret->is_active) == 0 ? 'selected' : '' }}>
                                Inactive
                            </option>
                        </select>
                        <small class="text-gray-500 text-xs">Inactive secrets will reject webhook requests</small>
                        @error('is_active')
                            <div class="w-full text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="w-full !p-4 bg-gray-50 border border-gray-200 rounded">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex-1">
                                <strong class="text-gray-800 text-sm">Current Secret:</strong>
                                <code class="!ml-2 !px-2 !py-1 bg-white text-xs rounded border border-gray-300">{{ substr($webhookSecret->secret, 0, 20) }}...</code>
                            </div>
                            <button type="button" id="copySecretBtn"
                                class="!px-3 !py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded transition-all"
                                data-secret="{{ $webhookSecret->secret }}">
                                Copy Full Secret
                            </button>
                        </div>
                        <small class="text-gray-600 text-xs block !mt-2">To change the secret, use the "Regenerate" button on the listing page</small>
                    </div>

                    <div class="w-full !p-4 bg-white border border-gray-200 rounded">
                        <label class="text-sm font-semibold text-gray-700 block !mb-3">Statistics</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="flex flex-col gap-1">
                                <span class="text-xs text-gray-500">Pending Domains</span>
                                <strong class="text-lg text-gray-900">{{ $webhookSecret->pending_domains_count }}</strong>
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-xs text-gray-500">Secret Rotated</span>
                                <strong class="text-sm text-gray-900">{{ $webhookSecret->secret_rotated_at ? $webhookSecret->secret_rotated_at->format('Y-m-d H:i') : 'Never' }}</strong>
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-xs text-gray-500">Last Used</span>
                                <strong class="text-sm text-gray-900">{{ $webhookSecret->last_used_at ? $webhookSecret->last_used_at->diffForHumans() : 'Never' }}</strong>
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-xs text-gray-500">Created</span>
                                <strong class="text-sm text-gray-900">{{ $webhookSecret->created_at->format('Y-m-d H:i') }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="w-full flex items-center gap-2 !p-2">
                        <button type="submit"
                            class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                            Update Webhook Secret
                        </button>
                        <a href="{{ route('admin.webhook-secrets.index') }}"
                            class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 transition-all bg-gray-200 hover:bg-gray-300 text-gray-800 rounded cursor-pointer">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('copySecretBtn').addEventListener('click', function() {
            const secret = this.getAttribute('data-secret');
            const button = this;
            const originalText = button.textContent;

            // Try modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(secret).then(function() {
                    showSuccess(button, originalText);
                }).catch(function() {
                    fallbackCopy(secret, button, originalText);
                });
            } else {
                fallbackCopy(secret, button, originalText);
            }
        });

        function fallbackCopy(text, button, originalText) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.select();

            try {
                document.execCommand('copy');
                showSuccess(button, originalText);
            } catch (err) {
                alert('Failed to copy. Please copy manually.');
            }

            document.body.removeChild(textArea);
        }

        function showSuccess(button, originalText) {
            button.textContent = 'Copied!';
            button.classList.add('copy-success');

            setTimeout(function() {
                button.textContent = originalText;
                button.classList.remove('copy-success');
            }, 2000);
        }
    </script>
@endpush
