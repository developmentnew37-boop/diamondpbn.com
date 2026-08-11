<div class="flex flex-col gap-3 content-card w-full min-w-0 campaign-list-toolbar">
    <div
        class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap md:flex-nowrap sm:items-stretch sm:justify-end min-w-0">
        <div class="w-full sm:w-auto sm:min-w-[200px] sm:max-w-xs shrink-0 flex items-center">
            @include('admin.campaigns.partials.campaign-owner-filter')
        </div>

        <div class="relative w-full sm:flex-1 sm:min-w-[12rem] sm:max-w-md min-w-0 flex items-center">
            <form method="GET" action="{{ url()->current() }}" class="relative w-full">
                @foreach (request()->except(['search', 'page']) as $key => $value)
                    @continue(is_array($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <input type="search" name="search" placeholder="{{ $searchPlaceholder ?? 'Search campaign no...' }}" id="search_category"
                    value="{{ request('search') }}"
                    class="bg-gray-100 shadow border border-gray-200 h-12 !px-3 !pr-[50px] text-sm leading-normal w-full rounded outline-none focus:border-[var(--primary-color)]">

                <button type="submit"
                    class="w-12 h-12 flex items-center justify-center bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] absolute top-0 right-0 rounded-r transition-colors"
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
