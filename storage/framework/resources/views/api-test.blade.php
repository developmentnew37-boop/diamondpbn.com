<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('API Test') }}
        </h2>
    </x-slot>

    <div class="max-w-4xl" x-data="apiTester()">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-6 space-y-6">
                {{-- API Key --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('API Key') }}</label>
                    <input type="password" x-model="apiKey" placeholder="hlk_your_api_key_here"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Get your API key from') }} <a href="{{ route('settings.edit') }}" class="text-indigo-600 hover:underline">Settings → API Keys</a></p>
                </div>

                {{-- Tabs --}}
                <div class="border-b border-gray-200">
                    <nav class="flex gap-4">
                        <button type="button" @click="tab = 'get'"
                            :class="tab === 'get' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-1 border-b-2 font-medium text-sm">GET {{ __('List Links') }}</button>
                        <button type="button" @click="tab = 'post'"
                            :class="tab === 'post' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-1 border-b-2 font-medium text-sm">POST {{ __('Create Links') }}</button>
                        <button type="button" @click="tab = 'delete'"
                            :class="tab === 'delete' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-2 px-1 border-b-2 font-medium text-sm">DELETE {{ __('Delete by URL') }}</button>
                    </nav>
                </div>

                {{-- GET Tab --}}
                <div x-show="tab === 'get'" x-cloak>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Search (optional)') }}</label>
                                <input type="text" x-model="getParams.search" placeholder="URL or keyword"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Per page') }}</label>
                                <input type="number" x-model="getParams.per_page" min="1" max="100"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                        </div>
                        <button type="button" @click="sendGet()" :disabled="loading"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            <span x-show="loading && tab === 'get'" class="animate-spin mr-2">⏳</span>
                            {{ __('Send GET Request') }}
                        </button>
                    </div>
                </div>

                {{-- POST Tab --}}
                <div x-show="tab === 'post'" x-cloak>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Payload (JSON)') }}</label>
                            <textarea x-model="postBody" rows="8"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                                placeholder='[
  {"url": "https://example.com/page1", "keyword": "admin", "nofollow": false},
  {"url": "https://example.com/page2", "keyword": "casino", "nofollow": true}
]'></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Batch ID') }}</label>
                                <input type="number" x-model="postParams.batch_id" min="0"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Chunk ID') }}</label>
                                <input type="number" x-model="postParams.chunk_id" min="0"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                        </div>
                        <button type="button" @click="sendPost()" :disabled="loading"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            <span x-show="loading && tab === 'post'" class="animate-spin mr-2">⏳</span>
                            {{ __('Send POST Request') }}
                        </button>
                    </div>
                </div>

                {{-- DELETE Tab --}}
                <div x-show="tab === 'delete'" x-cloak>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('URL to delete') }}</label>
                            <input type="url" x-model="deleteUrl" placeholder="https://example.com/page"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <p class="mt-1 text-xs text-gray-500">{{ __('Deletes all links with this exact URL.') }}</p>
                        </div>
                        <button type="button" @click="sendDelete()" :disabled="loading"
                            class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:opacity-50">
                            <span x-show="loading && tab === 'delete'" class="animate-spin mr-2">⏳</span>
                            {{ __('Send DELETE Request') }}
                        </button>
                    </div>
                </div>

                {{-- Response --}}
                <div x-show="response !== null" x-cloak class="border-t border-gray-200 pt-6">
                    <h4 class="text-sm font-semibold text-gray-900 mb-2">{{ __('Response') }}</h4>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-medium" :class="statusClass" x-text="'HTTP ' + status"></span>
                    </div>
                    <pre class="p-4 bg-gray-50 rounded-lg text-sm overflow-x-auto max-h-80 overflow-y-auto" x-text="responseText"></pre>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
