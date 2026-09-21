<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="max-w-8xl" x-data="settingsAccordion({{ json_encode(session('status') === 'api-key-created' ? 'api' : 'site') }})">
        @if (in_array(session('status'), ['settings-updated', 'profile-updated', 'password-updated', 'api-key-created', 'api-key-deleted']))
            <div class="mb-6 p-4 rounded-lg {{ session('status') === 'settings-updated' ? 'bg-green-100 border border-green-400 text-green-700' : (session('status') === 'profile-updated' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-green-100 border border-green-400 text-green-700') }}">
                @if (session('status') === 'settings-updated') {{ __('Site settings saved successfully.') }}
                @elseif (session('status') === 'profile-updated') {{ __('Profile updated successfully.') }}
                @elseif (session('status') === 'password-updated') {{ __('Password updated successfully.') }}
                @elseif (session('status') === 'api-key-created') {{ __('API key created. Copy it now — it won\'t be shown again.') }}
                @elseif (session('status') === 'api-key-deleted') {{ __('API key deleted successfully.') }}
                @endif
            </div>
        @endif

        <div class="space-y-2">
            {{-- Site Settings Accordion --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <button type="button"
                    @click="toggle('site')"
                    class="w-full flex items-center justify-between px-6 py-4 text-left font-semibold text-gray-900 hover:bg-gray-50 transition-colors">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ __('Site Settings') }}
                    </span>
                    <svg class="w-5 h-5 text-gray-500 transition-transform" :class="{ 'rotate-180': isOpen('site') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="isOpen('site')" x-transition class="border-t border-gray-200" x-cloak>
                    <div class="p-6">
                        <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
                            @csrf
                            @method('PUT')
                            <div class="grid gap-6">
                                <div x-data="{ themeColor: '{{ old('theme_color', $settings->theme_color ?? 'indigo') }}' }">
                                    <x-input-label for="theme_color" :value="__('Theme Color')" />
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        @foreach(['indigo' => 'Indigo', 'blue' => 'Blue', 'purple' => 'Purple', 'teal' => 'Teal', 'emerald' => 'Emerald', 'violet' => 'Violet'] as $key => $label)
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="theme_color" value="{{ $key }}"
                                                    {{ ($settings->theme_color ?? 'indigo') === $key ? 'checked' : '' }}
                                                    x-model="themeColor"
                                                    class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="theme_color" value="custom"
                                                {{ ($settings->theme_color ?? '') === 'custom' ? 'checked' : '' }}
                                                x-model="themeColor"
                                                class="rounded-full border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="text-sm font-medium text-gray-700">{{ __('Custom') }}</span>
                                        </label>
                                    </div>
                                    <div class="mt-3 flex items-center gap-3" x-show="themeColor === 'custom'" x-cloak x-transition>
                                        <input type="color" name="theme_custom_hex" id="theme_custom_hex"
                                            value="{{ old('theme_custom_hex', $settings->theme_custom_hex ?? '#667eea') }}"
                                            oninput="document.getElementById('theme_custom_hex_text').value=this.value"
                                            class="h-10 w-14 cursor-pointer rounded border border-gray-300 p-1 bg-white">
                                        <input type="text" id="theme_custom_hex_text"
                                            value="{{ old('theme_custom_hex', $settings->theme_custom_hex ?? '#667eea') }}"
                                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-24"
                                            placeholder="#667eea" maxlength="7"
                                            oninput="var v=this.value; if(v&&!v.startsWith('#')) v='#'+v; document.getElementById('theme_custom_hex').value=v; this.value=v;">
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500" x-show="themeColor === 'custom'" x-cloak>
                                        {{ __('Pick a color or use presets above.') }}
                                    </p>
                                </div>
                                <div>
                                    <x-input-label for="site_title" :value="__('Site Title')" />
                                    <x-text-input id="site_title" name="site_title" type="text" class="mt-1 block w-full"
                                        :value="old('site_title', $settings->site_title)" required />
                                    <x-input-error :messages="$errors->get('site_title')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="site_description" :value="__('Site Description')" />
                                    <textarea id="site_description" name="site_description" rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('site_description', $settings->site_description) }}</textarea>
                                    <x-input-error :messages="$errors->get('site_description')" class="mt-2" />
                                </div>
                                <div class="border-t border-gray-200 pt-6">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Index Page (Home)') }}</h4>
                                    <p class="text-xs text-gray-500 mb-3">{{ __('Heading and description shown in the hero section on the home page.') }}</p>
                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label for="index_heading" :value="__('Index Page Heading')" />
                                            <x-text-input id="index_heading" name="index_heading" type="text" class="mt-1 block w-full"
                                                :value="old('index_heading', $settings->index_heading ?? '')" placeholder="Understanding Local Citations" />
                                            <x-input-error :messages="$errors->get('index_heading')" class="mt-2" />
                                        </div>
                                        <div>
                                            <x-input-label for="index_description" :value="__('Index Page Description')" />
                                            <textarea id="index_description" name="index_description" rows="3"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Discover how local citations can transform your business's online presence...">{{ old('index_description', $settings->index_description) }}</textarea>
                                            <x-input-error :messages="$errors->get('index_description')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                                <div class="border-t border-gray-200 pt-6">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Index Page Box (First section)') }}</h4>
                                    <p class="text-xs text-gray-500 mb-3">{{ __('Heading and content for the first content box below the hero (e.g. country keywords SERP section).') }}</p>
                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label for="index_box_heading" :value="__('Box Heading')" />
                                            <x-text-input id="index_box_heading" name="index_box_heading" type="text" class="mt-1 block w-full"
                                                :value="old('index_box_heading', $settings->index_box_heading ?? '')" placeholder="INDONESIA COUNTRY KEYWORDS SERP" />
                                            <x-input-error :messages="$errors->get('index_box_heading')" class="mt-2" />
                                        </div>
                                        <div>
                                            <x-input-label for="index_box_content" :value="__('Box Content (rich text)')" />
                                            <textarea id="index_box_content" name="index_box_content" rows="10"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Leave empty to show default hero or BEFORE/AFTER layout. Use the toolbar for headings, paragraphs, links, and more.">{{ old('index_box_content', $settings->index_box_content) }}</textarea>
                                            <p class="mt-1 text-xs text-gray-500">{{ __('Heading, paragraph, link, lists, and more. Leave empty to use Index Page Heading/Description or default layout.') }}</p>
                                            <x-input-error :messages="$errors->get('index_box_content')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                                <div class="border-t border-gray-200 pt-6">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Contact / About Us Page') }}</h4>
                                    <p class="text-xs text-gray-500 mb-3">{{ __('Heading and box content for the Contact Us (About) page.') }}</p>
                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label for="contact_heading" :value="__('Box Heading')" />
                                            <x-text-input id="contact_heading" name="contact_heading" type="text" class="mt-1 block w-full"
                                                :value="old('contact_heading', $settings->contact_heading ?? '')" placeholder="Contact Us" />
                                            <x-input-error :messages="$errors->get('contact_heading')" class="mt-2" />
                                        </div>
                                        <div>
                                            <x-input-label for="contact_box_content" :value="__('Box Content (rich text)')" />
                                            <textarea id="contact_box_content" name="contact_box_content" rows="10"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Advertising Inquiries, email, Telegram...">{{ old('contact_box_content', $settings->contact_box_content) }}</textarea>
                                            <p class="mt-1 text-xs text-gray-500">{{ __('Use the toolbar for headings, paragraphs, links, and more.') }}</p>
                                            <x-input-error :messages="$errors->get('contact_box_content')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                                <div class="border-t border-gray-200 pt-6">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Meta Tags (SEO)') }}</h4>
                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label for="meta_title" :value="__('Meta Title')" />
                                            <x-text-input id="meta_title" name="meta_title" type="text" class="mt-1 block w-full"
                                                :value="old('meta_title', $settings->meta_title)" />
                                            <x-input-error :messages="$errors->get('meta_title')" class="mt-2" />
                                        </div>
                                        <div>
                                            <x-input-label for="meta_description" :value="__('Meta Description')" />
                                            <textarea id="meta_description" name="meta_description" rows="2"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('meta_description', $settings->meta_description) }}</textarea>
                                            <x-input-error :messages="$errors->get('meta_description')" class="mt-2" />
                                        </div>
                                        <div>
                                            <x-input-label for="meta_keywords" :value="__('Meta Keywords (comma separated)')" />
                                            <textarea id="meta_keywords" name="meta_keywords" rows="2"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('meta_keywords', $settings->meta_keywords) }}</textarea>
                                            <x-input-error :messages="$errors->get('meta_keywords')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <x-primary-button>{{ __('Save Site Settings') }}</x-primary-button>
                        </form>

                        {{-- Index page before/after images (separate from main form to avoid nested forms) --}}
                        <div class="border-t border-gray-200 pt-6 mt-6">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Index page before/after images') }}</h4>
                            <p class="text-xs text-gray-500 mb-3">{{ __('Cards shown on the home page with BEFORE and AFTER images. Add or edit entries below.') }}</p>
                            <div class="space-y-4">
                                <form method="POST" action="{{ route('index-page-images.store') }}" enctype="multipart/form-data" class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    @csrf
                                    <p class="text-sm font-medium text-gray-700 mb-3">{{ __('Add new') }}</p>
                                    <div class="flex flex-wrap gap-4 items-end">
                                        <div class="min-w-[180px]">
                                            <x-input-label for="new_title" :value="__('Card title')" />
                                            <x-text-input id="new_title" name="title" type="text" class="mt-1 block w-full" value="{{ old('new_title') }}" placeholder="e.g. Indonesia SERP" required />
                                        </div>
                                        <div class="min-w-[140px]">
                                            <x-input-label for="new_before_image" :value="__('Before image')" />
                                            <input type="file" id="new_before_image" name="before_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700" required />
                                        </div>
                                        <div class="min-w-[140px]">
                                            <x-input-label for="new_after_image" :value="__('After image')" />
                                            <input type="file" id="new_after_image" name="after_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700" required />
                                        </div>
                                        <x-primary-button type="submit">{{ __('Add') }}</x-primary-button>
                                    </div>
                                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                                    <x-input-error :messages="$errors->get('before_image')" class="mt-2" />
                                    <x-input-error :messages="$errors->get('after_image')" class="mt-2" />
                                </form>
                                <div x-data="{ editingId: null, imageModalOpen: false }">
                                    <button type="button" @click="imageModalOpen = true" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                                        {{ __('Manage before/after images') }}
                                    </button>
                                    {{-- Modal for image list and edit --}}
                                    <div x-show="imageModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                                        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                                            <div x-show="imageModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="imageModalOpen = false"></div>
                                            <div x-show="imageModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="relative inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                                                <h3 class="text-lg font-semibold text-gray-900 mb-4" id="modal-title">{{ __('Before/after image cards') }}</h3>
                                                <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                                                    @foreach($indexPageImages ?? [] as $img)
                                                    <div class="flex flex-wrap items-center gap-4 p-4 border border-gray-200 rounded-lg">
                                                        <div class="flex items-center gap-3 min-w-0">
                                                            <div class="shrink-0 flex gap-2">
                                                                <img src="{{ $img->before_image_url }}" alt="Before" class="w-16 h-12 object-cover rounded border border-gray-200" />
                                                                <img src="{{ $img->after_image_url }}" alt="After" class="w-16 h-12 object-cover rounded border border-gray-200" />
                                                            </div>
                                                            <span class="font-medium text-gray-800">{{ $img->title }}</span>
                                                        </div>
                                                        <div class="flex gap-2 ml-auto">
                                                            <button type="button" @click="editingId = editingId === {{ $img->id }} ? null : {{ $img->id }}" class="text-sm text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</button>
                                                            <form method="POST" action="{{ route('index-page-images.destroy', $img) }}" class="inline" onsubmit="return confirm('{{ __('Remove this before/after card?') }}');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="text-sm text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                                                            </form>
                                                        </div>
                                                        <div class="w-full" x-show="editingId === {{ $img->id }}" x-cloak x-transition>
                                                            <form method="POST" action="{{ route('index-page-images.update', $img) }}" enctype="multipart/form-data" class="mt-3 pt-3 border-t border-gray-200 flex flex-wrap gap-4 items-end">
                                                                @csrf
                                                                @method('PUT')
                                                                <div class="min-w-[180px]">
                                                                    <x-input-label :value="__('Card title')" />
                                                                    <x-text-input name="title" type="text" class="mt-1 block w-full" value="{{ $img->title }}" required />
                                                                </div>
                                                                <div class="min-w-[140px]">
                                                                    <x-input-label :value="__('Before image (optional)')" />
                                                                    <input type="file" name="before_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700" />
                                                                </div>
                                                                <div class="min-w-[140px]">
                                                                    <x-input-label :value="__('After image (optional)')" />
                                                                    <input type="file" name="after_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700" />
                                                                </div>
                                                                <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    @endforeach
                                                </div>
                                                @if(empty($indexPageImages) || ($indexPageImages && count($indexPageImages) === 0))
                                                <p class="text-sm text-gray-500 py-4">{{ __('No before/after images yet. Add one above.') }}</p>
                                                @endif
                                                <div class="mt-4 flex justify-end">
                                                    <button type="button" @click="imageModalOpen = false" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">{{ __('Close') }}</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- API Key Accordion --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <button type="button"
                    @click="toggle('api')"
                    class="w-full flex items-center justify-between px-6 py-4 text-left font-semibold text-gray-900 hover:bg-gray-50 transition-colors">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        {{ __('API Keys') }}
                    </span>
                    <svg class="w-5 h-5 text-gray-500 transition-transform" :class="{ 'rotate-180': isOpen('api') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="isOpen('api')" x-transition class="border-t border-gray-200" x-cloak>
                    <div class="p-6 space-y-6">
                        @if (session('status') === 'api-key-created' && session('api_key_plain'))
                            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4">
                                <p class="text-sm font-medium text-amber-800 mb-2">{{ __('Your new API key (copy now — it won\'t be shown again):') }}</p>
                                <div class="flex items-center gap-2">
                                    <code class="flex-1 px-3 py-2 bg-white border border-amber-200 rounded text-sm font-mono break-all">{{ session('api_key_plain') }}</code>
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ session('api_key_plain') }}'); this.textContent='{{ __('Copied!') }}'; setTimeout(()=>this.textContent='{{ __('Copy') }}',2000)"
                                        class="shrink-0 px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded text-sm font-medium text-amber-900">{{ __('Copy') }}</button>
                                </div>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('api-keys.store') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="flex-1 min-w-[200px]">
                                <x-input-label for="api_key_name" :value="__('Key name (optional)')" />
                                <x-text-input id="api_key_name" name="name" type="text" class="mt-1 block w-full"
                                    :value="old('name')" placeholder="{{ __('e.g. Production, Mobile app') }}" />
                            </div>
                            <x-primary-button type="submit">{{ __('Generate API Key') }}</x-primary-button>
                        </form>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 mb-2">{{ __('Your API Keys') }}</h4>
                            <p class="text-sm text-gray-500 mb-3">{{ __('Use the API key in requests:') }} <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">Authorization: Bearer YOUR_KEY</code> {{ __('or') }} <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">X-API-Key: YOUR_KEY</code></p>
                            <p class="text-sm text-gray-500 mb-3">{{ __('API endpoint:') }} <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">GET {{ url('/api/hidden-links') }}</code></p>
                            @if ($apiKeys->isEmpty())
                                <p class="text-gray-500 text-sm">{{ __('No API keys yet. Generate one above.') }}</p>
                            @else
                                <ul class="divide-y divide-gray-200 border border-gray-200 rounded-lg">
                                    @foreach ($apiKeys as $key)
                                        <li class="flex items-center justify-between px-4 py-3">
                                            <div>
                                                <span class="font-mono text-sm text-gray-700">{{ $key->name ?: __('Unnamed') }}</span>
                                                <span class="font-mono text-sm text-gray-500 ml-2">{{ $key->key_prefix }}...</span>
                                                @if ($key->last_used_at)
                                                    <span class="text-xs text-gray-400 ml-2">{{ __('Last used') }} {{ $key->last_used_at->diffForHumans() }}</span>
                                                @endif
                                            </div>
                                            <form method="POST" action="{{ route('api-keys.destroy', $key) }}" class="inline" onsubmit="return confirm('{{ __('Revoke this API key?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:text-red-700">{{ __('Revoke') }}</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Profile Accordion --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <button type="button"
                    @click="toggle('profile')"
                    class="w-full flex items-center justify-between px-6 py-4 text-left font-semibold text-gray-900 hover:bg-gray-50 transition-colors">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        {{ __('Profile Information') }}
                    </span>
                    <svg class="w-5 h-5 text-gray-500 transition-transform" :class="{ 'rotate-180': isOpen('profile') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="isOpen('profile')" x-transition class="border-t border-gray-200" x-cloak>
                    <div class="p-6">@include('profile.partials.update-profile-information-form')</div>
                </div>
            </div>

            {{-- Password Accordion --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <button type="button"
                    @click="toggle('password')"
                    class="w-full flex items-center justify-between px-6 py-4 text-left font-semibold text-gray-900 hover:bg-gray-50 transition-colors">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        {{ __('Update Password') }}
                    </span>
                    <svg class="w-5 h-5 text-gray-500 transition-transform" :class="{ 'rotate-180': isOpen('password') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="isOpen('password')" x-transition class="border-t border-gray-200" x-cloak>
                    <div class="p-6">@include('profile.partials.update-password-form')</div>
                </div>
            </div>

            {{-- Delete Account Accordion --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <button type="button"
                    @click="toggle('delete')"
                    class="w-full flex items-center justify-between px-6 py-4 text-left font-semibold text-gray-900 hover:bg-gray-50 transition-colors">
                    <span class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        {{ __('Delete Account') }}
                    </span>
                    <svg class="w-5 h-5 text-gray-500 transition-transform" :class="{ 'rotate-180': isOpen('delete') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="isOpen('delete')" x-transition class="border-t border-gray-200" x-cloak>
                    <div class="p-6">@include('profile.partials.delete-user-form')</div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toolbar = [
                [{ 'header': [1, 2, 3, 4, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'indent': '-1'}, { 'indent': '+1' }],
                ['blockquote', 'code-block'],
                ['link'],
                [{ 'align': [] }],
                ['clean']
            ];
            function initQuill(taId, containerId) {
                var ta = document.getElementById(taId);
                if (!ta) return;
                var container = document.createElement('div');
                container.id = containerId;
                container.className = 'bg-white border border-gray-300 rounded-md mt-1';
                ta.parentNode.insertBefore(container, ta);
                ta.style.display = 'none';
                var quill = new Quill(container, { theme: 'snow', modules: { toolbar: toolbar } });
                quill.root.innerHTML = ta.value || '';
                ta.form.addEventListener('submit', function() { ta.value = quill.root.innerHTML; });
            }
            initQuill('index_box_content', 'quill-editor-container');
            initQuill('contact_box_content', 'quill-contact-container');
        });
    </script>
    @endpush
</x-app-layout>
