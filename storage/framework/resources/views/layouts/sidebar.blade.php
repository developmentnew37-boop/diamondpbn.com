@php
    $siteSettings = \App\Models\Setting::getSettings();
    $themeGradient = $siteSettings->theme_gradient_css ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
@endphp
<aside
    :class="{ 'translate-x-0': sidebarOpen }"
    @click.away="sidebarOpen = false"
    class="fixed inset-y-0 left-0 z-50 w-64 h-[100vh] transform -translate-x-full lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen lg:self-start shrink-0 shadow-xl lg:shadow-none transition-transform duration-200 ease-out"
    style="background: {{ $themeGradient }}">
    <div class="flex flex-col h-full min-h-0">
        {{-- Logo / Brand --}}
        <div class="flex items-center justify-center h-16 px-4 border-b border-white/10">
            <a href="{{ route('landing.index') }}" class="flex items-center gap-2 text-white font-bold text-lg min-w-0" title="{{ $siteSettings->site_title }}">
                <span class="text-2xl shrink-0">📍</span>
                <span class="truncate block" style="max-width: 11rem;">{{ Str::limit($siteSettings->site_title, 28) }}</span>
            </a>
        </div>

        {{-- Nav Links --}}
        <nav class="flex-1 min-h-0 px-4 py-6 space-y-1 overflow-y-auto overscroll-contain">
            <a href="{{ route('landing.index') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('landing.*') ? 'bg-white/25 text-white' : 'text-white/90 hover:bg-white/15 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                {{ __('Home') }}
            </a>
            <a href="{{ route('dashboard') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('dashboard') ? 'bg-white/25 text-white' : 'text-white/90 hover:bg-white/15 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                {{ __('Dashboard') }}
            </a>
            <a href="{{ route('hidden-links.index') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('hidden-links.*') ? 'bg-white/25 text-white' : 'text-white/90 hover:bg-white/15 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                {{ __('Hidden Links') }}
            </a>
            <a href="{{ route('api-test') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('api-test') ? 'bg-white/25 text-white' : 'text-white/90 hover:bg-white/15 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                {{ __('API Test') }}
            </a>
            <a href="{{ route('settings.edit') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all {{ request()->routeIs('settings.*') ? 'bg-white/25 text-white' : 'text-white/90 hover:bg-white/15 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                {{ __('Settings') }}
            </a>
        </nav>

        {{-- User & Logout --}}
        <div class="p-4 border-t border-white/10 shrink-0">
            <div class="flex items-center gap-3 px-4 py-2 mb-3">
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white font-semibold">
                    {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-white/70 truncate">{{ Auth::user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-3 w-full px-4 py-3 rounded-xl text-sm font-medium text-white/90 hover:bg-white/15 transition-all">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    {{ __('Log Out') }}
                </button>
            </form>
        </div>
    </div>
</aside>
