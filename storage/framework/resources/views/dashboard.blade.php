<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl space-y-6">
        {{-- Overview Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-100 rounded-lg">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ __('Total Hidden Links') }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $totalLinks }}</p>
                    </div>
                </div>
            </div>
            <a href="{{ route('hidden-links.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:border-indigo-300 hover:shadow-md transition-all group">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-100 rounded-lg group-hover:bg-indigo-200 transition-colors">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ __('Manage Links') }}</p>
                        <p class="text-indigo-600 font-semibold group-hover:underline">{{ __('View & add links →') }}</p>
                    </div>
                </div>
            </a>
        </div>

        {{-- Recent Links --}}
        @if ($recentLinks->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Recent Links') }}</h3>
                    <a href="{{ route('hidden-links.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">{{ __('View all') }}</a>
                </div>
                <div class="divide-y divide-gray-200">
                    @foreach ($recentLinks as $link)
                        <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
                            <div class="min-w-0 flex-1">
                                <a href="{{ $link->url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline truncate block">{{ Str::limit($link->url, 70) }}</a>
                                <p class="text-sm text-gray-500 mt-0.5 truncate" title="{{ $link->keyword ?? __('No keyword') }}">{{ Str::limit($link->keyword ?? __('No keyword'), 50) }} · {{ $link->created_at->format('M d, Y') }}</p>
                            </div>
                            <a href="{{ route('hidden-links.index') }}" class="ml-4 text-sm text-gray-500 hover:text-gray-700 shrink-0">{{ __('Manage') }}</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Quick Action --}}
        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Need to add links?') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Add single links or bulk import multiple URLs at once.') }}</p>
                </div>
                <a href="{{ route('hidden-links.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 shrink-0">
                    {{ __('Go to Hidden Links') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
