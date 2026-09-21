@php
    $siteSettings = \App\Models\Setting::getSettings();
    $contactHeading = $siteSettings->contact_heading ?? 'Contact Us';
    $contactContent = $siteSettings->contact_box_content;
@endphp
@extends('layouts.landing')

@section('title', 'Contact - ' . ($siteSettings->site_title ?? 'Local Citation Links'))

@push('style')
    <style>
        .contact-data-sec a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
        }

        .contact-data-sec a:hover {
            color: #4338ca;
            text-decoration: underline;
        }

        .contact-data-sec h2 {
            font-size: 42px;
            color: #000;
            font-weight: 700;
            margin-bottom: 15px;
        }
    </style>
@endpush

@section('content')
    <section
        class="bg-gradient-to-br from-gray-50 to-gray-100 py-12 md:py-14 px-6 md:px-10 text-center border-b border-gray-200 mb-12 md:mb-14 ">
        {{-- <h2 class="text-3xl md:text-4xl font-bold m-0 mb-4 text-gray-800 tracking-tight"
            @if (strlen($contactHeading) > 60) title="{{ $contactHeading }}" @endif>{{ Str::limit($contactHeading, 60) }}
        </h2> --}}
        @if ($contactContent)
            <div
                class="contact-data-sec text-lg  m-0 max-w-[700px] mx-auto prose prose-gray prose-p:mb-2 prose-a:text-indigo-500 prose-a:no-underline hover:prose-a:underline flex flex-col gap-3">
                {!! $contactContent !!}
            </div>
        @else
            <p class="text-lg text-gray-600 m-0 max-w-[700px] mx-auto mb-2">Advertising Inquiries</p>
            <p class="text-lg text-gray-600 m-0 max-w-[700px] mx-auto mb-2">Looking to advertise with us or sponsor a
                feature? Contact us for detailed information and opportunities:</p>
            <p class="text-lg text-gray-600 m-0 max-w-[700px] mx-auto mb-2">Email: <a href="mailto:rocketpbns@gmail.com"
                    class="text-indigo-500 hover:text-indigo-600 font-medium">rocketpbns@gmail.com</a></p>
            <p class="text-lg text-gray-600 m-0 max-w-[700px] mx-auto">Telegram: <a href="https://t.me/hunter_no_1"
                    target="_blank" rel="noopener noreferrer"
                    class="text-indigo-500 hover:text-indigo-600 font-medium">@hunter_no_1</a></p>
        @endif
    </section>

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
@endsection
