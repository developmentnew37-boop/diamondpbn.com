@php
    $activeStatusFilter = $statusFilter ?? request('status', '');
    $postStatusFilters = [
        '' => ['label' => 'All', 'count' => (int) ($statusCounts->total ?? 0)],
        'success' => ['label' => 'Success', 'count' => (int) ($statusCounts->success ?? 0)],
        'failed' => ['label' => 'Failed', 'count' => (int) ($statusCounts->failed ?? 0)],
        'queued' => [
            'label' => ! empty($isConvertedLiveCampaign) ? 'Waiting' : 'Queued',
            'count' => (int) ($statusCounts->queued ?? 0),
        ],
    ];
@endphp
<div class="w-full flex flex-wrap items-center gap-2 !mb-3">
    <span class="text-sm font-medium text-gray-600">Filter by status:</span>
    @foreach ($postStatusFilters as $filterValue => $filter)
        @php
            $isActive = $activeStatusFilter === $filterValue;
            $routeParams = [$routeParameter => $campaign];
            if ($filterValue !== '') {
                $routeParams['status'] = $filterValue;
            }
            $filterUrl = route($routeName, $routeParams);
        @endphp
        <a href="{{ $filterUrl }}"
            class="inline-flex items-center gap-1 rounded !px-3 !py-1.5 text-sm font-medium border transition-colors
            {{ $isActive
                ? 'bg-[var(--primary-color)] text-white border-[var(--primary-color)]'
                : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}">
            {{ $filter['label'] }}
            <span class="rounded-full !px-1.5 text-xs {{ $isActive ? 'bg-white/20' : 'bg-gray-100' }}">
                {{ $filter['count'] }}
            </span>
        </a>
    @endforeach
</div>
