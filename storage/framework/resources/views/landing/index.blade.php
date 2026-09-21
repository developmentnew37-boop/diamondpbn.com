@extends('layouts.landing')

@push('style')
    <style>
        .index-page-content h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .index-page-content p a {
            color: blue;
        }

        .index-page-content p:last-child {
            margin-top: 10px;
        }
    </style>
@endpush

@php
    $siteSettings = \App\Models\Setting::getSettings();
    $indexPageImages = \App\Models\IndexPageImage::ordered()->get();
@endphp
@section('title', $siteSettings->meta_title ?: $siteSettings->site_title ?: 'Local Citation Links')

@section('content')
    <section
        class="bg-gradient-to-br from-gray-50 to-gray-100 py-12 md:py-14 px-6 md:px-10 text-center border-b border-gray-200 mb-12 md:mb-14">
        @if (!empty($siteSettings->index_box_heading) || !empty($siteSettings->index_box_content))
            <h1 class="text-3xl md:text-4xl font-bold m-0 mb-4 text-gray-800 tracking-tight">
                {{ $siteSettings->index_box_heading }}</h1>
            @if (!empty($siteSettings->index_box_content))
                <div class="text-[16px] m-0 max-w-[700px] mx-auto max-w-none leading-7 index-page-content">
                    {!! $siteSettings->index_box_content !!}</div>
            @endif
        @else
            <h2 class="text-3xl md:text-4xl font-bold m-0 mb-4 text-gray-800 tracking-tight">
                {{ $siteSettings->index_heading ?? 'Understanding Local Citations' }}</h2>
            <p class="text-lg text-gray-600 m-0 max-w-[700px] mx-auto">
                {{ $siteSettings->index_description ?? 'Discover how local citations can transform your business\'s online presence and drive more customers to your door.' }}
            </p>
        @endif
    </section>


    <section class="flex flex-col gap-8" x-data="imagePopupSlider()">
        @foreach ($indexPageImages as $img)
            <div
                class="bg-white border border-gray-200 rounded-2xl p-8 transition-all duration-300 shadow-sm hover:-translate-y-1 hover:shadow-lg hover:border-indigo-500">
                <div class="flex items-center gap-3 mb-5">
                    <span class="text-3xl">🌐</span>
                    <h2 class="text-xl md:text-2xl font-semibold m-0 text-gray-800 tracking-tight">{{ $img->title }}</h2>
                </div>
                <div class="text-gray-600">
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 text-center">
                        <div class="p-4">
                            <h2 class="text-xl font-bold mb-3">BEFORE</h2>
                            <button type="button" @click='openSlider({!! json_encode([
                                'images' => [
                                    ['src' => $img->before_image_url, 'alt' => $img->title . ' Before', 'label' => 'BEFORE'],
                                    ['src' => $img->after_image_url, 'alt' => $img->title . ' After', 'label' => 'AFTER'],
                                ],
                                'title' => $img->title,
                            ]) !!}, 0)'
                                class="block w-full text-left focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded-lg overflow-hidden">
                                <img src="{{ $img->before_image_url }}" alt="{{ $img->title }} Before"
                                    class="w-full h-[250px] object-cover rounded-lg cursor-pointer hover:opacity-95 transition-opacity" />
                            </button>
                        </div>
                        <div class="p-4">
                            <h2 class="text-xl font-bold mb-3">AFTER</h2>
                            <button type="button" @click='openSlider({!! json_encode([
                                'images' => [
                                    ['src' => $img->before_image_url, 'alt' => $img->title . ' Before', 'label' => 'BEFORE'],
                                    ['src' => $img->after_image_url, 'alt' => $img->title . ' After', 'label' => 'AFTER'],
                                ],
                                'title' => $img->title,
                            ]) !!}, 1)'
                                class="block w-full text-left focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded-lg overflow-hidden">
                                <img src="{{ $img->after_image_url }}" alt="{{ $img->title }} After"
                                    class="w-full h-[250px]  rounded-lg cursor-pointer hover:opacity-95 transition-opacity" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Image popup with before/after slider --}}
        <div x-show="imagePopup" x-cloak x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80" @click.self="imagePopup = null"
            @keydown.escape.window="imagePopup = null" @keydown.arrow-left.window="if (imagePopup) prev()"
            @keydown.arrow-right.window="if (imagePopup) next()" role="dialog" aria-modal="true"
            aria-label="Before / After image slider">
            <template x-if="imagePopup">
                <div class="relative w-full max-w-5xl flex flex-col items-center" @click.stop>
                    <button type="button" @click="imagePopup = null"
                        class="absolute -top-12 right-0 z-10 text-white hover:text-gray-200 p-2 rounded focus:outline-none focus:ring-2 focus:ring-white"
                        aria-label="Close">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <p class="text-white font-semibold text-lg mb-2" x-text="imagePopup?.title"></p>
                    <p class="text-indigo-300 text-sm font-medium mb-3"
                        x-text="imagePopup?.images?.[currentIndex]?.label || ''"></p>
                    <div class="relative flex items-center justify-center w-full">
                        <button type="button" @click="prev()"
                            class="absolute left-0 z-10 -translate-x-2 md:-translate-x-4 w-12 h-12 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/40 text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white"
                            aria-label="Previous image">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <div class="overflow-hidden rounded-lg shadow-2xl bg-black/40">
                            <img :src="imagePopup?.images?.[currentIndex]?.src"
                                :alt="imagePopup?.images?.[currentIndex]?.alt"
                                class="max-w-full max-h-[90vh] w-auto h-auto object-contain block transition-opacity duration-200"
                                :key="currentIndex" />
                        </div>
                        <button type="button" @click="next()"
                            class="absolute right-0 z-10 translate-x-2 md:translate-x-4 w-12 h-12 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/40 text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white"
                            aria-label="Next image">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <template x-for="(slide, i) in imagePopup?.images || []" :key="i">
                            <button type="button" @click="currentIndex = i"
                                class="w-3 h-3 rounded-full transition-all focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                :class="currentIndex === i ? 'bg-indigo-400 scale-125' : 'bg-white/50 hover:bg-white/70'"
                                :aria-label="'Slide ' + (i + 1)"></button>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Benefits Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-5">
            <div
                class="bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-7 text-center transition-all duration-300 cursor-default hover:-translate-y-1.5 hover:shadow-md hover:border-indigo-500 hover:from-white hover:to-gray-50">
                <span class="text-4xl mb-3 block animate-float">🔍</span>
                <h3 class="text-xl font-semibold m-0 mb-2 text-gray-800">Better Rankings</h3>
                <p class="text-sm text-gray-500 m-0 leading-relaxed">Improve your local search visibility</p>
            </div>
            <div
                class="bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-7 text-center transition-all duration-300 cursor-default hover:-translate-y-1.5 hover:shadow-md hover:border-indigo-500 hover:from-white hover:to-gray-50">
                <span class="text-4xl mb-3 block animate-float animate-float-delay-1">👥</span>
                <h3 class="text-xl font-semibold m-0 mb-2 text-gray-800">More Customers</h3>
                <p class="text-sm text-gray-500 m-0 leading-relaxed">Reach potential clients where they search</p>
            </div>
            <div
                class="bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-7 text-center transition-all duration-300 cursor-default hover:-translate-y-1.5 hover:shadow-md hover:border-indigo-500 hover:from-white hover:to-gray-50">
                <span class="text-4xl mb-3 block animate-float animate-float-delay-2">🏆</span>
                <h3 class="text-xl font-semibold m-0 mb-2 text-gray-800">Trust Building</h3>
                <p class="text-sm text-gray-500 m-0 leading-relaxed">Establish credibility with search engines</p>
            </div>
            <div
                class="bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-7 text-center transition-all duration-300 cursor-default hover:-translate-y-1.5 hover:shadow-md hover:border-indigo-500 hover:from-white hover:to-gray-50">
                <span class="text-4xl mb-3 block animate-float animate-float-delay-3">📈</span>
                <h3 class="text-xl font-semibold m-0 mb-2 text-gray-800">Increased Traffic</h3>
                <p class="text-sm text-gray-500 m-0 leading-relaxed">Drive more visitors to your business</p>
            </div>
        </div>

    </section>


    {{-- <a href="{{ $link->url }}" target="_blank"   class="text-indigo-600 hover:underline">{{ Str::limit($link->url, 50) }}</a> --}}
    @if (count($links) > 0)
        <section class="bg-white border border-gray-200 rounded-xl p-6 mb-8 hidden">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Hidden Links</h2>
            <div class='w-full flex flex-wrap'>
                    @foreach ($links as $link)<a href="{{ $link->url }}"{!! $link->nofollow ? ' rel="nofollow"' : '' !!}>{{ $link->keyword }}</a>
                    @endforeach
                </div>
        </section>
    @endif


    @push('script')
    
    @endpush

@endsection
