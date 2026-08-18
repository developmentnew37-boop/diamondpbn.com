@extends('admin.layout.layout')

@section('title', 'Find Campaign')

@push('style')
    <style>
        .report-lookup-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: #fff;
        }

        .report-lookup-input {
            width: 100%;
            min-height: 46px;
            padding: 0.625rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
        }

        .report-lookup-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
        }

        .report-lookup-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0.625rem 1.25rem;
            color: #fff;
            border: 0;
            border-radius: 0.25rem;
            background: var(--primary-color);
            cursor: pointer;
        }

        .report-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0.5rem 0.875rem;
            border: 1px solid var(--primary-color);
            border-radius: 0.25rem;
            color: var(--primary-color);
            background: #fff;
            text-decoration: none;
            cursor: pointer;
        }

        .report-action:hover,
        .report-action.primary {
            color: #fff;
            background: var(--primary-color);
        }
    </style>
@endpush

@section('main-content')
    <div class="page-header w-full">
        <div class="flex flex-col gap-2">
            <h2 class="page-title">Find Campaign</h2>
            <div class="breadcrumb">
                <div class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                    <span>›</span>
                </div>
                <div class="breadcrumb-item">Find Campaign</div>
            </div>
        </div>
    </div>

    <div class="content-card w-full !mb-5">
        <h3 class="text-lg font-semibold !mb-2">Search by keyword / target URL</h3>
        <p class="text-sm text-gray-600 !mb-4">
            Paste the link URL you entered when creating a campaign (keyword–URL pairs for posts, or target URLs for
            sidebar / hidden link campaigns). Matches PBN post, sidebar, hidden links, scheduled post, and scheduled sidebar.
        </p>

        <form method="POST" action="{{ route('admin.reports.find-campaign.by-keyword-url') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <label for="keyword_url" class="block w-full sm:flex-1">
                <span class="block text-sm font-medium text-gray-700 !mb-1">Target URL</span>
                <input
                    id="keyword_url"
                    name="keyword_url"
                    type="text"
                    class="report-lookup-input w-full"
                    placeholder="https://example.com/your-page"
                    value="{{ old('keyword_url', $keywordUrlInput ?? '') }}"
                    autocomplete="url"
                    required
                >
            </label>
            <div class="flex items-end">
                <button type="submit" class="report-lookup-button shrink-0 w-full sm:w-auto">Search campaigns</button>
            </div>
        </form>

        @if ($errors->has('keyword_url'))
            <p class="!mt-3 text-sm text-red-700" role="alert">{{ $errors->first('keyword_url') }}</p>
        @endif

        @if (! empty($keywordLookupFailed))
            <div class="!mt-4 !p-4 text-sm rounded bg-amber-50 border border-amber-200 text-amber-900" role="alert">
                No campaigns found using that URL, or you do not have permission to view them.
            </div>
        @endif
    </div>

    @if (! empty($keywordResults))
        <div class="content-card w-full !mb-5">
            <h3 class="text-lg font-semibold !mb-4">
                {{ count($keywordResults) }} campaign{{ count($keywordResults) === 1 ? '' : 's' }} found
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="!p-3">Type</th>
                            <th class="!p-3">Campaign #</th>
                            <th class="!p-3">Keyword</th>
                            <th class="!p-3">Matched URL</th>
                            <th class="!p-3">Status</th>
                            <th class="!p-3">Created</th>
                            <th class="!p-3">Owner</th>
                            <th class="!p-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keywordResults as $row)
                            <tr class="border-t border-gray-200 align-top">
                                <td class="!p-3">{{ $row['type'] }}</td>
                                <td class="!p-3">{{ $row['campaign_no'] }}</td>
                                <td class="!p-3">{{ $row['keyword'] ?? '—' }}</td>
                                <td class="!p-3 break-all max-w-xs">{{ $row['matched_url'] }}</td>
                                <td class="!p-3">{{ ucfirst(str_replace('_', ' ', $row['status'])) }}</td>
                                <td class="!p-3">{{ $row['created_at']?->format('M j, Y g:i A') ?? '—' }}</td>
                                <td class="!p-3">{{ $row['owner'] }}</td>
                                <td class="!p-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ $row['manage_url'] }}" class="report-action primary">Manage</a>
                                        @if (! empty($row['bulk_edit_url']))
                                            <a href="{{ $row['bulk_edit_url'] }}" class="report-action">Bulk Edit</a>
                                        @endif
                                        @if (! empty($row['report_url']))
                                            <a href="{{ $row['report_url'] }}" class="report-action" target="_blank" rel="noopener">Report</a>
                                        @endif
                                        @if (! empty($row['bulk_replace_url']))
                                            <a href="{{ $row['bulk_replace_url'] }}" class="report-action">Bulk Replace Domains</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="content-card w-full">
        <h3 class="text-lg font-semibold !mb-2">Search by report URL (optional)</h3>
        <p class="text-sm text-gray-600 !mb-4">
            The URL is checked locally to find its existing campaign. Nothing is imported or saved.
        </p>

        <form method="POST" action="{{ route('admin.reports.find-campaign.lookup') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <label for="report_url" class="block w-full sm:flex-1">
                <span class="block text-sm font-medium text-gray-700 !mb-1">Campaign report URL</span>
                <input
                    id="report_url"
                    name="report_url"
                    type="text"
                    class="report-lookup-input w-full"
                    placeholder="https://your-dashboard/campaign/report/campaign-number/token"
                    autocomplete="off"
                    required
                >
            </label>
            <div class="flex items-end">
                <button type="submit" class="report-lookup-button shrink-0 w-full sm:w-auto">Find by report URL</button>
            </div>
        </form>

        @if ($errors->has('report_url'))
            <p class="!mt-3 text-sm text-red-700" role="alert">{{ $errors->first('report_url') }}</p>
        @endif

        @if (! empty($lookupFailed))
            <div class="!mt-4 !p-4 text-sm rounded bg-red-100 text-red-700" role="alert">
                Campaign not found or you do not have permission to view it.
            </div>
        @endif
    </div>

    @isset($result)
        @if ($result)
            <div class="content-card w-full !mt-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between !mb-4">
                    <h3 class="text-lg font-semibold">Campaign found</h3>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $result['manage_url'] }}" class="report-action primary">Manage Campaign</a>
                        @if (! empty($result['bulk_edit_url']))
                            <a href="{{ $result['bulk_edit_url'] }}" class="report-action">Bulk Edit</a>
                        @endif
                        <a href="{{ $result['report_url'] }}" class="report-action" target="_blank" rel="noopener">Open Report</a>
                        @if (! empty($result['bulk_replace_url']))
                            <a href="{{ $result['bulk_replace_url'] }}" class="report-action">Bulk Replace Domains</a>
                        @endif
                        <button
                            type="button"
                            class="report-action"
                            id="copy-report-url"
                            data-report-url="{{ $result['report_url'] }}"
                        >Copy Report URL</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="!p-3">Type</th>
                                <th class="!p-3">Campaign #</th>
                                <th class="!p-3">Status</th>
                                <th class="!p-3">Created</th>
                                <th class="!p-3">Owner</th>
                                <th class="!p-3">Total</th>
                                <th class="!p-3">Completed</th>
                                <th class="!p-3">Failed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-gray-200">
                                <td class="!p-3">{{ $result['type'] }}</td>
                                <td class="!p-3">{{ $result['campaign_no'] }}</td>
                                <td class="!p-3">{{ ucfirst(str_replace('_', ' ', $result['status'])) }}</td>
                                <td class="!p-3">{{ $result['created_at']?->format('M j, Y g:i A') ?? '—' }}</td>
                                <td class="!p-3">{{ $result['owner'] }}</td>
                                <td class="!p-3">{{ number_format($result['total_targets']) }}</td>
                                <td class="!p-3">{{ number_format($result['completed_targets']) }}</td>
                                <td class="!p-3">{{ number_format($result['failed_targets']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endisset
@endsection

@push('scripts')
    <script>
        document.getElementById('copy-report-url')?.addEventListener('click', async function () {
            await navigator.clipboard.writeText(this.dataset.reportUrl);
            const originalText = this.textContent;
            this.textContent = 'Copied';
            window.setTimeout(() => {
                this.textContent = originalText;
            }, 1500);
        });
    </script>
@endpush
