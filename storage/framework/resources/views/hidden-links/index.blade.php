<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Hidden Links') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl space-y-6">
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg" role="alert">
                {{ session('success') }}
            </div>
        @endif

        {{-- Add Forms (Accordion-style) --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ showBulk: false }">
            <div class="border-b border-gray-200">
                <button type="button" @click="showBulk = false" :class="!showBulk ? 'border-b-2 border-indigo-500 text-indigo-600 bg-gray-50' : 'text-gray-600 hover:bg-gray-50'"
                    class="px-6 py-3 text-sm font-medium">
                    {{ __('Add Single Link') }}
                </button>
                <button type="button" @click="showBulk = true" :class="showBulk ? 'border-b-2 border-indigo-500 text-indigo-600 bg-gray-50' : 'text-gray-600 hover:bg-gray-50'"
                    class="px-6 py-3 text-sm font-medium">
                    {{ __('Bulk Add Links') }}
                </button>
            </div>
            <div class="p-6">
                {{-- Single Add Form --}}
                <div x-show="!showBulk" x-transition>
                    <form method="POST" action="{{ route('hidden-links.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <x-input-label for="url" :value="__('URL')" />
                                <x-text-input id="url" name="url" type="url" class="mt-1 block w-full" :value="old('url')" placeholder="https://example.com/page" required />
                                <x-input-error :messages="$errors->get('url')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="keyword" :value="__('Keyword')" />
                                <x-text-input id="keyword" name="keyword" type="text" class="mt-1 block w-full" :value="old('keyword')" placeholder="Optional keyword" />
                                <x-input-error :messages="$errors->get('keyword')" class="mt-2" />
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="nofollow" value="1" {{ old('nofollow') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">{{ __('Nofollow') }}</span>
                                </label>
                            </div>
                        </div>
                        <x-primary-button>{{ __('Add Link') }}</x-primary-button>
                    </form>
                </div>

                {{-- Bulk Add Form --}}
                <div x-show="showBulk" x-transition x-cloak style="display: none;" x-data="{
                    urlCount: 0,
                    keywordCount: 0,
                    countLines(el) {
                        return el ? el.value.split('\n').filter(l => l.trim()).length : 0;
                    },
                    updateCounts() {
                        this.urlCount = this.countLines(document.getElementById('bulk_urls'));
                        this.keywordCount = this.countLines(document.getElementById('bulk_keywords'));
                    }
                }" x-init="$nextTick(() => updateCounts())">
                    <form method="POST" action="{{ route('hidden-links.bulk-store') }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between">
                                    <x-input-label for="bulk_urls" :value="__('URLs (one per line)')" />
                                    <span class="text-sm font-medium text-indigo-600" x-text="urlCount + ' ' + (urlCount === 1 ? '{{ __('URL') }}' : '{{ __('URLs') }}')"></span>
                                </div>
                                <textarea id="bulk_urls" name="urls" rows="8" required
                                    @input="updateCounts()"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="https://example.com/page1&#10;https://example.com/page2&#10;https://example.com/page3">{{ old('urls') }}</textarea>
                                <p class="mt-1 text-sm text-gray-500">{{ __('Line 1 URL pairs with line 1 keyword.') }}</p>
                                <x-input-error :messages="$errors->get('urls')" class="mt-2" />
                            </div>
                            <div>
                                <div class="flex items-center justify-between">
                                    <x-input-label for="bulk_keywords" :value="__('Keywords (one per line)')" />
                                    <span class="text-sm font-medium text-indigo-600" x-text="keywordCount + ' ' + (keywordCount === 1 ? '{{ __('Keyword') }}' : '{{ __('Keywords') }}')"></span>
                                </div>
                                <textarea id="bulk_keywords" name="keywords" rows="8"
                                    @input="updateCounts()"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="keyword1&#10;keyword2&#10;keyword3">{{ old('keywords') }}</textarea>
                                <p class="mt-1 text-sm text-gray-500">{{ __('Line 1 keyword applies to line 1 URL. Empty = no keyword.') }}</p>
                                <x-input-error :messages="$errors->get('keywords')" class="mt-2" />
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="nofollow" value="1" {{ old('nofollow') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">{{ __('Nofollow (applies to all links)') }}</span>
                            </label>
                        </div>
                        <x-primary-button>{{ __('Add Links') }}</x-primary-button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Links Table --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('All Hidden Links') }}</h3>
                    <form method="GET" action="{{ route('hidden-links.index') }}" class="flex gap-2">
                        <input type="search" name="search" value="{{ request('search') }}"
                            placeholder="{{ __('Search URL or keyword...') }}"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-64">
                        <x-primary-button type="submit">{{ __('Search') }}</x-primary-button>
                        @if (request('search'))
                            <a href="{{ route('hidden-links.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-medium text-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">{{ __('Clear') }}</a>
                        @endif
                    </form>
                </div>
                @if ($hiddenLinks->isEmpty())
                    <p class="text-gray-500">{{ request('search') ? __('No links match your search.') : __('No hidden links yet. Add one using the form above.') }}</p>
                @else
                    <form method="POST" action="{{ route('hidden-links.bulk-destroy') }}" id="bulk-delete-form"
                        onsubmit="var n=document.querySelectorAll('input[name=\'ids[]\']:checked').length; return n>0 && confirm('{{ __('Delete :count selected link(s)?') }}'.replace(':count', n));">
                        @csrf
                        @method('DELETE')
                        @if (request('search'))
                            <input type="hidden" name="search" value="{{ request('search') }}">
                        @endif
                        <div class="mb-4" id="bulk-actions-bar" style="display: none;">
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                {{ __('Delete Selected') }}
                            </button>
                        </div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left w-10">
                                        <input type="checkbox" id="select-all"
                                            onchange="document.querySelectorAll('input[name=\'ids[]\']').forEach(c=>c.checked=this.checked); document.getElementById('bulk-actions-bar').style.display=this.checked?'block':'none';"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" title="{{ __('Select all on this page') }}">
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('URL') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Keyword') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Nofollow') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Date') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($hiddenLinks as $link)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" form="bulk-delete-form" name="ids[]" value="{{ $link->id }}"
                                                onchange="var any=document.querySelectorAll('input[name=\'ids[]\']:checked').length; document.getElementById('bulk-actions-bar').style.display=any?'block':'none'; document.getElementById('select-all').checked=any && any==document.querySelectorAll('input[name=\'ids[]\']').length;"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $loop->iteration + $hiddenLinks->firstItem() - 1 }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900 max-w-xs">
                                            <a href="{{ $link->url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline truncate block" title="{{ $link->url }}">{{ Str::limit($link->url, 60) }}</a>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 max-w-[200px]" title="{{ $link->keyword ?? '-' }}">{{ Str::limit($link->keyword ?? '-', 35) }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if ($link->nofollow)
                                                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">{{ __('Yes') }}</span>
                                            @else
                                                <span class="text-gray-400">{{ __('No') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">{{ $link->created_at->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <form method="POST" action="{{ route('hidden-links.destroy', $link) }}" class="inline" onsubmit="return confirm('{{ __('Are you sure you want to delete this link?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6">
                        {{ $hiddenLinks->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
