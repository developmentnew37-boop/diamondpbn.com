<div class="page-header">
    <div class="w-full flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="page-title">Bulk Replace Domains</h2>
            <div class="breadcrumb">
                <div class="breadcrumb-item">
                    <a href="{{ $backUrl }}" class="breadcrumb-link">
                        {{ $campaign->campaign_no }}
                    </a>
                    <span>›</span>
                </div>
                <div class="breadcrumb-item">Bulk replace domains</div>
            </div>
        </div>
        <a href="{{ $backUrl }}"
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

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 !mb-5">
        <div class="rounded border border-gray-200 !p-3">
            <div class="text-xs text-gray-500">Campaign</div>
            <div class="font-semibold">{{ $campaign->campaign_no }}</div>
        </div>
        <div class="rounded border border-gray-200 !p-3">
            <div class="text-xs text-gray-500">Status</div>
            <div class="font-semibold capitalize">{{ $campaign->status ?? '—' }}</div>
        </div>
    </div>

    @if (! $hasReplaceableItems)
        <div class="rounded border border-amber-200 bg-amber-50 text-amber-900 !p-4 !mb-4">
            This campaign has no queued or failed {{ $itemLabel }}s that can be replaced. Return to the campaign view and check task statuses.
        </div>
    @endif

    <form method="POST" action="{{ $storeRoute }}">
        @csrf

        <div class="rounded border border-gray-200 bg-gray-50 !px-3 !py-2 !mb-4 text-sm text-gray-700 flex flex-wrap items-center gap-x-4 gap-y-1">
            <span>
                Failed:
                <strong id="bulk-replace-failed-count" class="text-gray-900">0</strong>
                domain(s)
            </span>
            <span class="text-gray-400" aria-hidden="true">→</span>
            <span>
                Replacements:
                <strong id="bulk-replace-replacement-count" class="text-gray-900">0</strong>
                domain(s)
            </span>
            <span class="text-gray-400" aria-hidden="true">·</span>
            <span>
                Will map:
                <strong id="bulk-replace-pair-count" class="text-[var(--primary-color)]">0</strong>
                pair(s)
            </span>
            <span id="bulk-replace-count-warning" class="hidden text-red-600 text-xs font-medium"></span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 !mb-4">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 !mb-2">
                    <label for="failed_domains" class="font-medium">Failed domains</label>
                    <span class="text-xs text-gray-500">
                        <strong id="bulk-replace-failed-count-inline" class="text-gray-800">0</strong> domain(s) pasted
                    </span>
                </div>
                <textarea id="failed_domains" name="failed_domains" rows="14"
                    class="bulk-replace-domain-input w-full rounded border border-gray-200 bg-gray-50 !px-3 !py-3 font-mono text-sm"
                    placeholder="one domain per line&#10;failed1.example.com&#10;failed2.example.com">{{ old('failed_domains', $failedPrefill) }}</textarea>
                <p class="text-sm text-gray-500 !mt-2">
                    One domain slot per line. Line 1 maps to replacement line 1, line 2 to line 2, and so on.
                </p>
            </div>

            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 !mb-2">
                    <label for="replacement_domains" class="font-medium">Replacement domains</label>
                    <span class="text-xs text-gray-500">
                        <strong id="bulk-replace-replacement-count-inline" class="text-gray-800">0</strong> domain(s) pasted
                    </span>
                </div>
                <textarea id="replacement_domains" name="replacement_domains" rows="14"
                    class="bulk-replace-domain-input w-full rounded border border-gray-200 bg-gray-50 !px-3 !py-3 font-mono text-sm"
                    placeholder="one domain per line&#10;active1.example.com&#10;active2.example.com">{{ old('replacement_domains') }}</textarea>
                <p class="text-sm text-gray-500 !mt-2">
                    Each domain must exist in your inventory. You may provide fewer replacements than failed domains; unmapped failed lines are skipped.
                </p>
            </div>
        </div>

        <label for="reason" class="block font-medium !mb-2">Reason for replacement <span class="text-sm font-normal text-gray-500">(optional)</span></label>
        <textarea id="reason" name="reason" rows="3" maxlength="2000"
            class="w-full rounded border border-gray-200 bg-gray-50 !px-3 !py-3 !mb-4"
            placeholder="Optional note, e.g. bulk swap after server outages.">{{ old('reason') }}</textarea>

        @if (! empty($preview))
            <div class="rounded border border-blue-100 bg-blue-50 !p-4 !mb-4">
                <h3 class="font-semibold text-blue-900 !mb-2">Preview</h3>
                <div class="flex flex-col gap-1 text-sm text-blue-800">
                    @foreach ($preview as $row)
                        <div>
                            {{ $row['failed'] }} → {{ $row['replacement'] }}
                            <span class="text-blue-600">· {{ $row['task_count'] ?? $row['post_count'] ?? 0 }} {{ $itemLabelPlural }} affected</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <button type="submit"
            class="rounded bg-[var(--primary-color)] text-white !px-5 !py-3 disabled:cursor-not-allowed disabled:opacity-50"
            @disabled(! $hasReplaceableItems)>
            Replace domains and re-queue {{ $itemLabelPlural }}
        </button>
    </form>
</div>

@push('scripts')
    <script>
        (function () {
            function countDomainLines(value) {
                if (!value) {
                    return 0;
                }

                return value
                    .split(/\r?\n/)
                    .map(function (line) {
                        return line.trim();
                    })
                    .filter(function (line) {
                        return line !== '';
                    })
                    .length;
            }

            function setCount(id, count) {
                var el = document.getElementById(id);
                if (el) {
                    el.textContent = String(count);
                }
            }

            function updateBulkReplaceDomainCounts() {
                var failedInput = document.getElementById('failed_domains');
                var replacementInput = document.getElementById('replacement_domains');

                if (!failedInput || !replacementInput) {
                    return;
                }

                var failedCount = countDomainLines(failedInput.value);
                var replacementCount = countDomainLines(replacementInput.value);
                var pairCount = Math.min(failedCount, replacementCount);
                var warningEl = document.getElementById('bulk-replace-count-warning');

                setCount('bulk-replace-failed-count', failedCount);
                setCount('bulk-replace-failed-count-inline', failedCount);
                setCount('bulk-replace-replacement-count', replacementCount);
                setCount('bulk-replace-replacement-count-inline', replacementCount);
                setCount('bulk-replace-pair-count', pairCount);

                if (warningEl) {
                    if (replacementCount > failedCount && failedCount > 0) {
                        warningEl.textContent = 'Too many replacements for ' + failedCount + ' failed domain(s).';
                        warningEl.classList.remove('hidden');
                        replacementInput.classList.add('border-red-300');
                    } else {
                        warningEl.textContent = '';
                        warningEl.classList.add('hidden');
                        replacementInput.classList.remove('border-red-300');
                    }
                }
            }

            document.querySelectorAll('.bulk-replace-domain-input').forEach(function (textarea) {
                textarea.addEventListener('input', updateBulkReplaceDomainCounts);
                textarea.addEventListener('paste', function () {
                    setTimeout(updateBulkReplaceDomainCounts, 0);
                });
            });

            updateBulkReplaceDomainCounts();
        })();
    </script>
@endpush
