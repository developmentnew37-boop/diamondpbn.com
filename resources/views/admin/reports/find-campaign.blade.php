@extends('admin.layout.layout')

@section('title', 'Find Campaign')

@push('style')
    @include('admin.campaigns.partials.campaign-list-table-styles')
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

        .find-campaign-table thead th {
            background-color: #1f2937 !important;
            color: #ffffff !important;
            border-color: #374151 !important;
        }
    </style>
@endpush

@section('main-content')
    @php
        $clTh = 'border border-gray-200 font-sans !font-normal !px-2 !py-3 capitalize text-left';
        $clTd = 'border border-gray-200 font-sans !px-2 !py-3';
        $clTdCenter = 'border border-gray-200 font-sans !px-2 !py-3 text-center';
        $clTdProgress = 'border border-gray-200 font-sans !px-2 !py-3 min-w-[140px]';
        $clTdActions = 'actions-col border border-gray-200 font-sans !px-2 !py-3 min-w-[252px]';

        $resolveFindCampaignStatus = function (array $row): array {
            $total = (int) ($row['total_targets'] ?? 0);
            $completed = (int) ($row['completed_targets'] ?? 0);
            $failed = (int) ($row['failed_targets'] ?? 0);
            $pending = max($total - ($completed + $failed), 0);
            $raw = strtolower(str_replace([' ', '-'], '_', (string) ($row['status'] ?? '')));

            if ($pending > 0 || in_array($raw, ['running', 'publishing', 'queued'], true)) {
                if ($raw === 'queued' && $pending === $total) {
                    return ['queued', 'bg-gray-100 text-gray-600'];
                }
                if (in_array($raw, ['paused', 'cancelled'], true)) {
                    return [$raw === 'paused' ? 'paused' : 'cancelled', 'bg-gray-100 text-gray-600'];
                }

                return ['running', 'bg-yellow-100 text-yellow-700'];
            }

            if ($failed === $total && $total > 0) {
                return ['failed', 'bg-red-100 text-red-700'];
            }

            if ($completed === $total && $total > 0) {
                return ['complete', 'bg-green-100 text-green-700'];
            }

            if ($completed + $failed === $total && $total > 0) {
                return ['semi-complete', 'bg-orange-100 text-orange-700'];
            }

            return match ($raw) {
                'failed' => ['failed', 'bg-red-100 text-red-700'],
                'completed', 'complete' => ['complete', 'bg-green-100 text-green-700'],
                'semi_failed', 'semi_complete' => ['semi-complete', 'bg-orange-100 text-orange-700'],
                'paused' => ['paused', 'bg-gray-100 text-gray-600'],
                'cancelled' => ['cancelled', 'bg-gray-100 text-gray-600'],
                default => [str_replace('_', ' ', $raw) ?: 'queued', 'bg-gray-100 text-gray-600'],
            };
        };
    @endphp

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
        <div class="w-full flex flex-wrap justify-between items-start content-card !mb-5">
            <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
                {{ count($keywordResults) }} campaign{{ count($keywordResults) === 1 ? '' : 's' }} found
            </h2>

            <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                <table class="campaign-list-table find-campaign-table display w-full min-w-[1100px] border border-gray-200 border-collapse text-sm whitespace-nowrap">
                    <thead>
                        <tr>
                            @foreach ([
                                'sno',
                                'Type',
                                'Campaign No',
                                'Keyword',
                                'Total Targets',
                                'Completed',
                                'Failed',
                                'Pending',
                                'Progress',
                                'Status',
                                'Created At',
                                'Owner',
                                'Actions',
                            ] as $t)
                                <th @class([
                                    $clTh,
                                    'actions-col' => $t === 'Actions',
                                ])>{{ $t }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keywordResults as $index => $row)
                            @php
                                $total = (int) ($row['total_targets'] ?? 0);
                                $completed = (int) ($row['completed_targets'] ?? 0);
                                $failed = (int) ($row['failed_targets'] ?? 0);
                                $pending = max($total - ($completed + $failed), 0);
                                $progress = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
                                [$statusLabel, $statusClass] = $resolveFindCampaignStatus($row);
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="{{ $clTdCenter }}">{{ $index + 1 }}</td>
                                <td class="{{ $clTd }}">{{ $row['type'] }}</td>
                                <td class="{{ $clTd }}">
                                    <div>{{ $row['campaign_no'] }}</div>
                                    @if (! empty($row['matched_url']))
                                        <div class="text-[11px] text-gray-500 whitespace-normal break-all max-w-[220px]">
                                            {{ $row['matched_url'] }}
                                        </div>
                                    @endif
                                </td>
                                <td class="{{ $clTd }}">{{ $row['keyword'] ?? '—' }}</td>
                                <td class="{{ $clTdCenter }}">{{ $total }}</td>
                                <td class="{{ $clTdCenter }}">{{ $completed }}</td>
                                <td class="{{ $clTdCenter }}">{{ $failed }}</td>
                                <td class="{{ $clTdCenter }}">{{ $pending }}</td>
                                <td class="{{ $clTdProgress }}">
                                    @include('admin.campaigns.partials.campaign-list-progress', ['progress' => $progress])
                                </td>
                                <td class="{{ $clTdCenter }}">
                                    @include('admin.campaigns.partials.campaign-list-status-badge', [
                                        'label' => ucfirst($statusLabel),
                                        'statusClass' => $statusClass,
                                    ])
                                </td>
                                <td class="{{ $clTd }}">
                                    {{ $row['created_at']?->format('d-M-Y H:i') ?? '—' }}
                                </td>
                                <td class="{{ $clTd }}">{{ $row['owner'] }}</td>
                                <td class="{{ $clTdActions }}">
                                    @include('admin.campaigns.partials.campaign-list-actions', [
                                        'viewUrl' => $row['manage_url'],
                                        'editUrl' => $row['bulk_edit_url'] ?? null,
                                        'reportUrl' => $row['report_url'] ?? null,
                                        'bulkReplaceUrl' => $row['bulk_replace_url'] ?? null,
                                        'openReportInNewTab' => true,
                                    ])
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
            <div class="w-full flex flex-wrap justify-between items-start content-card !mt-5">
                <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
                    Campaign found
                </h2>

                @php
                    $total = (int) ($result['total_targets'] ?? 0);
                    $completed = (int) ($result['completed_targets'] ?? 0);
                    $failed = (int) ($result['failed_targets'] ?? 0);
                    $pending = max($total - ($completed + $failed), 0);
                    $progress = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
                    [$statusLabel, $statusClass] = $resolveFindCampaignStatus($result);
                @endphp

                <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                    <table class="campaign-list-table find-campaign-table display w-full min-w-[1100px] border border-gray-200 border-collapse text-sm whitespace-nowrap">
                        <thead>
                            <tr>
                                @foreach ([
                                    'Type',
                                    'Campaign No',
                                    'Total Targets',
                                    'Completed',
                                    'Failed',
                                    'Pending',
                                    'Progress',
                                    'Status',
                                    'Created At',
                                    'Owner',
                                    'Actions',
                                ] as $t)
                                    <th @class([
                                        $clTh,
                                        'actions-col' => $t === 'Actions',
                                    ])>{{ $t }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="hover:bg-gray-50">
                                <td class="{{ $clTd }}">{{ $result['type'] }}</td>
                                <td class="{{ $clTd }}">{{ $result['campaign_no'] }}</td>
                                <td class="{{ $clTdCenter }}">{{ $total }}</td>
                                <td class="{{ $clTdCenter }}">{{ $completed }}</td>
                                <td class="{{ $clTdCenter }}">{{ $failed }}</td>
                                <td class="{{ $clTdCenter }}">{{ $pending }}</td>
                                <td class="{{ $clTdProgress }}">
                                    @include('admin.campaigns.partials.campaign-list-progress', ['progress' => $progress])
                                </td>
                                <td class="{{ $clTdCenter }}">
                                    @include('admin.campaigns.partials.campaign-list-status-badge', [
                                        'label' => ucfirst($statusLabel),
                                        'statusClass' => $statusClass,
                                    ])
                                </td>
                                <td class="{{ $clTd }}">
                                    {{ $result['created_at']?->format('d-M-Y H:i') ?? '—' }}
                                </td>
                                <td class="{{ $clTd }}">{{ $result['owner'] }}</td>
                                <td class="{{ $clTdActions }}">
                                    @include('admin.campaigns.partials.campaign-list-actions', [
                                        'viewUrl' => $result['manage_url'],
                                        'editUrl' => $result['bulk_edit_url'] ?? null,
                                        'reportUrl' => $result['report_url'] ?? null,
                                        'bulkReplaceUrl' => $result['bulk_replace_url'] ?? null,
                                        'openReportInNewTab' => true,
                                    ])
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endisset
@endsection

@push('scripts')
    <script src="{{ asset('js/copy.js') }}"></script>
@endpush
