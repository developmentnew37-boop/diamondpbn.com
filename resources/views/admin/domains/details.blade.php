@extends('admin.layout.layout')

@section('title', 'Domain Details')

@push('style')
    <style>
        .domain-details-card {
            max-width: 720px;
        }

        .domain-details-field {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .domain-details-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }

        .domain-details-input {
            width: 100%;
            min-height: 46px;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            color: #111827;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 0.25rem;
            outline: none;
        }

        .domain-details-input:focus {
            border-color: var(--primary-color);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
        }

        .domain-details-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            color: #fff;
            background: var(--primary-color);
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
        }

        .domain-details-btn:hover {
            background: #e0410f;
        }

        .domain-details-info {
            background-color: rgba(255, 74, 23, 0.08);
            border: 1px solid rgba(255, 74, 23, 0.25);
            border-radius: 0.25rem;
            padding: 1rem;
            font-size: 0.875rem;
            color: #374151;
        }

        .domain-details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem 1.5rem;
        }

        @media (min-width: 640px) {
            .domain-details-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .domain-details-grid dt {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .domain-details-grid dd {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: #111827;
            word-break: break-word;
        }

        .domain-details-table thead th {
            background-color: #1f2937;
            color: #ffffff;
            font-weight: 500;
            text-align: left;
        }

        .domain-details-link {
            color: var(--primary-color);
            text-decoration: none;
        }

        .domain-details-link:hover {
            text-decoration: underline;
        }
    </style>
@endpush

@section('main-content')
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-full flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Domain Details</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.select.category') }}" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Domain Details</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full content-card !mb-5">
        <div class="px-6 pt-6 pb-8 flex flex-col gap-5 domain-details-card">
            <div class="domain-details-info">
                Enter a domain name or a full link. The page shows its category, who added it, when it was added, and the campaigns and domain sets that use it.
            </div>

            <form method="GET" action="{{ route('admin.domain.details') }}" class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="domain-details-field w-full">
                    <label class="domain-details-label" for="domain">Domain</label>
                    <input
                        id="domain"
                        name="domain"
                        type="text"
                        class="domain-details-input"
                        placeholder="example.com or https://example.com/page"
                        value="{{ $searched }}"
                        autocomplete="off"
                        maxlength="255"
                    >
                </div>
                <button type="submit" class="domain-details-btn w-full sm:w-auto">Search</button>
            </form>

            @if ($errors->has('domain'))
                <div class="!p-4 text-sm rounded bg-red-100 text-red-700" role="alert">
                    <span class="font-medium">{{ $errors->first('domain') }}</span>
                </div>
            @endif

            @if (is_array($details) && empty($details['found']))
                <div class="!p-4 text-sm rounded bg-red-100 text-red-700" role="alert">
                    <span class="font-medium">This domain is not in the inventory.</span>
                </div>
            @endif
        </div>
    </div>

    @if (! empty($details['found']))
        <div class="w-full content-card !mb-5">
            <div class="px-6 pt-6 pb-8">
                <h3 class="text-lg font-semibold !mb-4">{{ $details['name'] }}</h3>
                <dl class="domain-details-grid">
                    <div>
                        <dt>Category</dt>
                        <dd>{{ $details['category'] !== '' ? $details['category'] : '—' }}</dd>
                    </div>
                    <div>
                        <dt>Added by</dt>
                        <dd>{{ $details['added_by'] !== '' ? $details['added_by'] : '—' }}</dd>
                    </div>
                    <div>
                        <dt>Added</dt>
                        <dd>{{ $details['created_at'] !== '' ? $details['created_at'] : '—' }}</dd>
                    </div>
                    <div>
                        <dt>Last update</dt>
                        <dd>{{ $details['updated_at'] !== '' ? $details['updated_at'] : '—' }}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>
                            <span @class([
                                'inline-block rounded px-2 py-1 text-xs font-medium',
                                'bg-green-100 text-green-700' => $details['status'] === 'Connected',
                                'bg-red-100 text-red-700' => $details['status'] !== 'Connected',
                            ])>{{ $details['status'] }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Last seen</dt>
                        <dd>{{ $details['last_seen_at'] !== '' ? $details['last_seen_at'] : '—' }}</dd>
                    </div>
                    <div>
                        <dt>Agent version</dt>
                        <dd>{{ $details['agent_version'] !== '' ? $details['agent_version'] : '—' }}</dd>
                    </div>
                    <div>
                        <dt>Last status</dt>
                        <dd>{{ $details['last_status_message'] !== '' ? $details['last_status_message'] : '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="w-full content-card !mb-5">
            <div class="px-6 pt-6 pb-8">
                <h3 class="text-lg font-semibold !mb-4">Campaigns</h3>
                @if ($details['campaign_total'] === 0)
                    <p class="text-sm text-gray-600">This domain is not used in any campaign.</p>
                @else
                    <p class="text-sm text-gray-700 !mb-1">
                        Used in {{ number_format($details['campaign_total']) }} {{ $details['campaign_total'] === 1 ? 'campaign' : 'campaigns' }}.
                    </p>
                    <p class="text-sm text-gray-600 !mb-4">
                        @foreach ($details['campaign_counts'] as $type => $count)
                            <span class="inline-block !mr-3">{{ $type }} {{ number_format($count) }}</span>
                        @endforeach
                    </p>
                    @if ($details['campaigns']->isEmpty())
                        <p class="text-sm text-gray-600">No campaigns on this page.</p>
                    @else
                        <div class="overflow-x-auto w-full">
                            <table class="domain-details-table w-full min-w-[640px] border border-gray-200 border-collapse text-sm">
                                <thead>
                                    <tr>
                                        <th class="border border-gray-700 !px-3 !py-3">S.No</th>
                                        <th class="border border-gray-700 !px-3 !py-3">Type</th>
                                        <th class="border border-gray-700 !px-3 !py-3">Campaign no</th>
                                        <th class="border border-gray-700 !px-3 !py-3">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($details['campaigns'] as $index => $campaign)
                                        <tr class="hover:bg-gray-50">
                                            <td class="border border-gray-200 !px-3 !py-3 text-center">{{ $details['campaigns']->firstItem() + $index }}</td>
                                            <td class="border border-gray-200 !px-3 !py-3">{{ $campaign['type'] }}</td>
                                            <td class="border border-gray-200 !px-3 !py-3">
                                                @if ($campaign['url'])
                                                    <a class="domain-details-link" href="{{ $campaign['url'] }}">{{ $campaign['campaign_no'] }}</a>
                                                @else
                                                    {{ $campaign['campaign_no'] }}
                                                @endif
                                            </td>
                                            <td class="border border-gray-200 !px-3 !py-3">{{ $campaign['created_at'] !== '' ? $campaign['created_at'] : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <div class="!mt-4">
                        {{ $details['campaigns']->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div class="w-full content-card !mb-5">
            <div class="px-6 pt-6 pb-8">
                <h3 class="text-lg font-semibold !mb-4">Domain sets</h3>
                @if ($details['sets'] === [])
                    <p class="text-sm text-gray-600">This domain is not in any domain set.</p>
                @else
                    <ul class="flex flex-col gap-2">
                        @foreach ($details['sets'] as $set)
                            <li>
                                <a class="domain-details-link text-sm" href="{{ $set['url'] }}">{{ $set['name'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
@endsection
