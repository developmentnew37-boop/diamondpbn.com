<div class="flex flex-col gap-3 content-card w-full min-w-0 campaign-list-toolbar">
    <div class="w-full flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between xl:gap-4 min-w-0">
        @if ($showStatusFilter ?? true)
            @php
                $activeStatusFilter = (string) request('status', '');
                $campaignStatusFilters = [
                    '' => 'All',
                    'queued' => 'Queued',
                    'running' => 'Running',
                    'completed' => 'Completed',
                    'failed' => 'Failed',
                    'semi_failed' => 'Semi-complete',
                ];
            @endphp
            <div class="w-full xl:flex-1 min-w-0 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <span class="text-sm font-medium text-gray-600 shrink-0">Filter by status:</span>
                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 min-w-0">
                    @foreach ($campaignStatusFilters as $filterValue => $label)
                        @php
                            $isActive = $activeStatusFilter === $filterValue;
                            $query = request()->except(['status', 'page']);
                            if ($filterValue !== '') {
                                $query['status'] = $filterValue;
                            }
                            $filterUrl = url()->current().(empty($query) ? '' : '?'.http_build_query($query));
                        @endphp
                        <a href="{{ $filterUrl }}"
                            class="inline-flex items-center rounded !px-2.5 sm:!px-3 !py-1 sm:!py-1.5 text-xs sm:text-sm font-medium border transition-colors whitespace-nowrap
                            {{ $isActive
                                ? 'bg-[var(--primary-color)] text-white border-[var(--primary-color)]'
                                : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div
            class="w-full xl:w-auto xl:shrink-0 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end min-w-0">
            <div class="w-full sm:w-auto sm:min-w-[11rem] sm:max-w-[16rem] shrink-0 flex items-center">
                @include('admin.campaigns.partials.campaign-owner-filter')
            </div>

            <div class="relative w-full sm:flex-1 sm:min-w-[12rem] sm:max-w-md xl:w-72 xl:flex-none min-w-0 flex items-center">
                <form method="GET" action="{{ url()->current() }}" class="relative w-full">
                    @foreach (request()->except(['search', 'page']) as $key => $value)
                        @continue(is_array($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <input type="search" name="search" placeholder="{{ $searchPlaceholder ?? 'Search campaign no...' }}" id="search_category"
                        value="{{ request('search') }}"
                        class="bg-gray-100 shadow border border-gray-200 h-11 sm:h-12 !px-3 !pr-[50px] text-sm leading-normal w-full rounded outline-none focus:border-[var(--primary-color)]">

                    <button type="submit"
                        class="w-11 h-11 sm:w-12 sm:h-12 flex items-center justify-center bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] absolute top-0 right-0 rounded-r transition-colors"
                        aria-label="Search campaigns">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
