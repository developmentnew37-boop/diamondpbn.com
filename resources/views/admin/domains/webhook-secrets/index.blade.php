@extends('admin.layout.layout')

@push('style')
    <style>
        .webhook-secrets-search {
            max-width: 320px;
        }

        .secret-display {
            font-family: 'Courier New', monospace;
            background: #f9fafb;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            word-break: break-all;
            border: 1px solid #e5e7eb;
        }

        .page-header-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
            gap: 0.5rem;
        }

        @media (min-width: 640px) {
            .page-header-actions {
                justify-content: flex-end;
            }
        }

        .page-header-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 42px;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 400;
            line-height: 1;
            color: #fff;
            border-radius: 0.25rem;
            white-space: nowrap;
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
            text-decoration: none;
        }

        .page-header-btn-primary {
            background-color: var(--primary-color);
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

        .theme-info-box {
            background-color: rgba(255, 74, 23, 0.08);
            border: 1px solid rgba(255, 74, 23, 0.25);
            border-radius: 0.25rem;
        }

        .theme-info-box strong {
            color: #c2410c;
        }

        .theme-info-box code {
            color: #9a3412;
            background: #fff;
            border: 1px solid rgba(255, 74, 23, 0.2);
        }

        .theme-info-box p {
            color: #9a3412;
        }

        .theme-badge {
            background-color: var(--primary-color);
            color: #fff;
        }

        .theme-badge-soft {
            background-color: rgba(255, 74, 23, 0.12);
            color: #c2410c;
        }

        .copy-icon-btn.copy-success {
            background-color: #10b981 !important;
        }

        .copy-icon-btn.copy-success .material-symbols-outlined {
            color: #fff !important;
        }
    </style>
@endpush

@section('title', 'Webhook Secrets')

@section('main-content')
    {{-- Breadcrumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Webhook Secrets</h2>
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
                        <a href="#" class="breadcrumb-link">Webhook Secrets</a>
                    </div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 page-header-actions">
                <a href="{{ route('admin.pending-domains.index') }}" class="page-header-btn page-header-btn-secondary">
                    <span class="material-symbols-outlined !text-base">pending_actions</span>
                    View Pending Domains
                </a>
                <a href="{{ route('admin.webhook-secrets.create') }}" class="page-header-btn page-header-btn-primary">
                    <span class="material-symbols-outlined !text-base">add</span>
                    Create Webhook Secret
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

        @if (session('warning'))
            <div class="!p-4 text-sm rounded bg-yellow-100 text-yellow-700 w-full" role="alert">
                <span class="font-medium">{{ session('warning') }}</span>
            </div>
        @endif

        @if (session('one_time_webhook_secret'))
            <div class="!p-4 text-sm rounded bg-blue-50 border border-blue-200 text-blue-900 w-full" role="alert">
                <strong class="block !mb-2">Copy this secret now (also available anytime via Reveal / Copy in the table).</strong>
                <div class="flex flex-wrap items-center gap-2">
                    <code id="oneTimeWebhookSecret" class="break-all">{{ session('one_time_webhook_secret') }}</code>
                    <button type="button"
                        class="copy-icon-btn inline-flex items-center justify-center rounded bg-blue-600 text-white w-8 h-8 border-0 cursor-pointer"
                        title="Copy secret"
                        onclick="copyToClipboard(@js(session('one_time_webhook_secret')), this)">
                        <span class="material-symbols-outlined !text-base">content_copy</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Auto-rotation settings --}}
    <div class="w-full content-card !mt-4">
        <div class="!p-4">
            <h3 class="text-base font-semibold text-gray-800 !mb-3">Automatic Secret Rotation</h3>
            <form action="{{ route('admin.webhook-secrets.rotation-settings') }}" method="POST"
                class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                @csrf
                <div class="flex flex-col gap-2 min-w-[220px]">
                    <label for="rotation_hours" class="text-sm font-medium text-gray-700">Rotate active secrets every</label>
                    <select name="rotation_hours" id="rotation_hours"
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">
                        @foreach ($rotationHourOptions as $hours)
                            <option value="{{ $hours }}" @selected((int) $rotationSetting->rotation_hours === (int) $hours)>
                                @if ($hours === 0)
                                    Off (manual regenerate only)
                                @elseif ($hours === 1)
                                    1 hour
                                @else
                                    {{ $hours }} hours
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit"
                    class="page-header-btn page-header-btn-primary !min-h-[46px]">
                    Save Rotation Setting
                </button>
            </form>
            <div class="!mt-4 !p-3 rounded border border-gray-200 bg-gray-50 text-xs text-gray-600 space-y-1">
                @if ($rotationSetting->isEnabled())
                    <p><strong>Status:</strong> Enabled — every {{ $rotationSetting->rotation_hours }} hour(s).</p>
                    <p><strong>Scheduler:</strong> Checks hourly; rotates secrets whose token is older than the interval.</p>
                    @if ($dueForRotationCount > 0)
                        <p class="text-amber-700"><strong>{{ $dueForRotationCount }}</strong> active secret(s) due for rotation on the next run.</p>
                    @else
                        <p>No secrets are due right now.</p>
                    @endif
                @else
                    <p><strong>Status:</strong> Disabled — use <em>Regenerate</em> on each secret manually when needed.</p>
                @endif
                <p class="!mt-2 text-amber-800"><strong>Important:</strong> After rotation, external partners must update the secret in their integration or webhook calls will fail until they do.</p>
            </div>
        </div>
    </div>

    {{-- Info Box --}}
    <div class="w-full content-card !mt-4">
        <div class="!p-4 theme-info-box">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">info</span>
                <div class="flex-1 min-w-0">
                    <strong class="text-sm">Webhook Endpoint:</strong>
                    <code class="!px-2 !py-1 rounded text-xs !ml-2 break-all">{{ url('/api/webhook/domains') }}</code>
                    <p class="text-xs !mt-1">Use this endpoint to submit pending domains. Include the secret in your POST request.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Webhook Secrets Table --}}
    <div class="w-full content-card min-w-0 !mt-4">
        <div class="!p-4 !pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <label for="webhookSecretsSearch" class="text-sm font-medium text-gray-700 shrink-0">Search secrets</label>
            <input type="search" id="webhookSecretsSearch" placeholder="Filter by name, status, id..."
                class="webhook-secrets-search bg-gray-100 border border-gray-200 !p-2 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">
        </div>
        <div class="overflow-x-auto !p-4 !pt-3">
            <table id="webhookSecretsTable" class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-gray-800 text-white">
                    <tr>
                        <th class="!px-6 !py-3">ID</th>
                        <th class="!px-6 !py-3">Name</th>
                        <th class="!px-6 !py-3">Secret</th>
                        <th class="!px-6 !py-3">Status</th>
                        <th class="!px-6 !py-3">Pending Domains</th>
                        <th class="!px-6 !py-3">Last Used</th>
                        <th class="!px-6 !py-3">Created</th>
                        <th class="!px-6 !py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($secrets as $secret)
                        @php
                            $plainSecret = null;
                            try {
                                $plainSecret = $secret->secret;
                            } catch (\Throwable) {
                                $plainSecret = null;
                            }
                        @endphp
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <td class="!px-6 !py-4">{{ $secret->id }}</td>
                            <td class="!px-6 !py-4 font-medium text-gray-900">{{ $secret->name }}</td>
                            <td class="!px-6 !py-4">
                                @if ($plainSecret)
                                    <div class="flex items-center gap-2 min-w-[220px]">
                                        <code
                                            id="webhook-secret-{{ $secret->id }}"
                                            class="secret-display secret-masked"
                                            data-secret="{{ $plainSecret }}"
                                            data-masked="••••••••••••">••••••••••••</code>
                                        <button type="button"
                                            class="copy-icon-btn inline-flex items-center justify-center rounded bg-gray-700 text-white w-8 h-8 border-0 cursor-pointer shrink-0"
                                            title="Reveal / hide secret"
                                            onclick="toggleWebhookSecret({{ $secret->id }}, this)">
                                            <span class="material-symbols-outlined !text-base">visibility</span>
                                        </button>
                                        <button type="button"
                                            class="copy-icon-btn inline-flex items-center justify-center rounded bg-blue-600 text-white w-8 h-8 border-0 cursor-pointer shrink-0"
                                            title="Copy secret"
                                            onclick="copyToClipboard(@js($plainSecret), this)">
                                            <span class="material-symbols-outlined !text-base">content_copy</span>
                                        </button>
                                    </div>
                                @else
                                    <code class="secret-display text-red-600">Unable to decrypt</code>
                                @endif
                            </td>
                            <td class="!px-6 !py-4">
                                @if ($secret->is_active)
                                    <span class="!px-2 !py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @else
                                    <span class="!px-2 !py-1 text-xs font-semibold rounded-full theme-badge-soft">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="!px-6 !py-4 text-center">
                                @if ($secret->pending_domains_count > 0)
                                    <span class="!px-2 !py-1 text-xs font-semibold rounded-full theme-badge-soft">
                                        {{ $secret->pending_domains_count }}
                                    </span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="!px-6 !py-4">
                                {{ $secret->last_used_at ? $secret->last_used_at->diffForHumans() : 'Never' }}
                            </td>
                            <td class="!px-6 !py-4">
                                {{ $secret->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="!px-6 !py-4">
                                <div class="flex gap-2 justify-center flex-nowrap">
                                    <a href="{{ route('admin.webhook-secrets.edit', $secret->id) }}"
                                        title="Edit secret"
                                        class="bg-yellow-500 flex items-center justify-center rounded w-7 h-7 duration-300 hover:bg-yellow-600">
                                        <span class="material-symbols-outlined !text-sm text-white">edit_square</span>
                                    </a>

                                    <form action="{{ route('admin.webhook-secrets.regenerate', $secret->id) }}"
                                        method="POST" class="inline"
                                        onsubmit="return confirm('Regenerate this secret? All integrations using the old secret will stop working.')">
                                        @csrf
                                        <button type="submit"
                                            title="Regenerate secret"
                                            class="bg-[var(--sidebar-bg)] flex items-center justify-center rounded w-7 h-7 duration-300 hover:bg-[var(--primary-color)] cursor-pointer">
                                            <span class="material-symbols-outlined !text-sm text-white">autorenew</span>
                                        </button>
                                    </form>

                                    <form action="{{ route('admin.webhook-secrets.destroy', $secret->id) }}"
                                        method="POST" class="inline"
                                        onsubmit="return confirm('Delete this webhook secret?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            title="Delete secret"
                                            class="bg-red-600 flex items-center justify-center rounded w-7 h-7 duration-300 hover:bg-red-700 cursor-pointer">
                                            <span class="material-symbols-outlined !text-sm text-white">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="!mt-4">
            {{ $secrets->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('webhookSecretsSearch');
            const table = document.getElementById('webhookSecretsTable');

            if (searchInput && table) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.trim().toLowerCase();
                    table.querySelectorAll('tbody tr').forEach(function(row) {
                        row.style.display = !query || row.textContent.toLowerCase().includes(query) ? '' : 'none';
                    });
                });
            }
        });

        function toggleWebhookSecret(id, button) {
            const el = document.getElementById('webhook-secret-' + id);
            if (!el) return;

            const icon = button.querySelector('.material-symbols-outlined');
            const revealed = el.classList.toggle('secret-revealed');

            if (revealed) {
                el.textContent = el.dataset.secret || '';
                el.classList.remove('secret-masked');
                if (icon) icon.textContent = 'visibility_off';
                button.title = 'Hide secret';
            } else {
                el.textContent = el.dataset.masked || '••••••••••••';
                el.classList.add('secret-masked');
                if (icon) icon.textContent = 'visibility';
                button.title = 'Reveal / hide secret';
            }
        }

        function copyToClipboard(text, button) {
            const icon = button.querySelector('.material-symbols-outlined');
            const originalIcon = icon ? icon.textContent : '';

            const done = function(success) {
                if (!icon) {
                    if (!success) alert('Failed to copy. Please copy manually.');
                    return;
                }

                if (success) {
                    icon.textContent = 'check';
                    button.classList.add('copy-success');
                    setTimeout(function() {
                        icon.textContent = originalIcon;
                        button.classList.remove('copy-success');
                    }, 2000);
                } else {
                    alert('Failed to copy. Please copy manually.');
                }
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    done(true);
                }).catch(function() {
                    done(fallbackCopy(text));
                });
                return;
            }

            done(fallbackCopy(text));
        }

        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.top = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();

            let success = false;
            try {
                success = document.execCommand('copy');
            } catch (err) {
                console.error('Copy failed:', err);
            }

            document.body.removeChild(textarea);
            return success;
        }
    </script>
@endpush
