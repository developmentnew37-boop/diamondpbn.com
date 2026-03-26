@extends('admin.layout.layout')

@section('title', 'Edit Schedule Campaign Post')

@push('style')
    <style>
        #article-container .ck-editor__editable_inline {
            min-height: 300px !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/45.2.0/ckeditor5.css" crossorigin>
@endpush

@section('main-content')

    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.schedule.campaign.index') }}" class="breadcrumb-link">Schedule Campaigns</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.schedule.campaign.show', $campaign->id) }}"
                            class="breadcrumb-link">{{ $campaign->campaign_no }}</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="javascript:void(0)" class="breadcrumb-link">Edit post</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                <a href="{{ route('admin.schedule.campaign.show', $campaign->id) }}"
                    class="!px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm">Back to campaign</a>
            </div>
        </div>
    </div>

    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1">
            @if (session()->has('cus__success'))
                <div class="w-full content-card">
                    <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium">{{ session('cus__success') }}</span>
                    </div>
                </div>
            @endif
            @if (session()->has('cus__error'))
                <div class="w-full content-card">
                    <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        <span class="font-medium">{{ session('cus__error') }}</span>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <form action="{{ route('admin.schedule.campaign.update.post', $campaignPost->id) }}" method="POST"
        class="w-full flex flex-col gap-2 content-card items-start">
        @csrf

        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
            Edit post: {{ \Illuminate\Support\Str::limit($fetchedData['post_title'] ?? 'Scheduled Post', 50) }}
        </h2>

        <div class="w-full flex flex-col gap-3 p-2">
            <label for="post_title"
                class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                Title
            </label>
            <input type="text" name="name" value="{{ $fetchedData['post_title'] ?? '' }}" id="post_title"
                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
        </div>

        <div class="w-full flex flex-col gap-3 p-2 !mt-6" id="article-container">
            <label for="update-editor"
                class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                Description
            </label>
            <textarea name="description" id="update-editor" rows="20">{!! $fetchedData['post_content'] ?? '' !!}</textarea>
        </div>

        <div class="w-full !mt-6">
            <button type="submit"
                class="!p-3 text-[16px] cursor-pointer bg-black text-white rounded hover:bg-[var(--primary-color)] w-full">
                Update post on remote site
            </button>
        </div>
    </form>

@endsection

@push('scripts')
    <script type="importmap">
{
    "imports": {
        "ckeditor5": "{{ asset('ckeditor/ckeditor5.js') }}",
        "ckeditor5/": "{{ asset('ckeditor/') }}/",
        "ckeditor5-premium-features": "{{ asset('ckeditor/ckeditor5-premium-features.js') }}",
        "ckeditor5-premium-features/": "{{ asset('ckeditor/') }}/"
    }
}
</script>

    <script src="https://cdn.ckbox.io/ckbox/2.6.1/ckbox.js" crossorigin></script>
    <script type="module" src="{{ asset('ckeditor/main.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const contentEl = document.querySelector('.ck-content');
                if (!contentEl) return;
                contentEl.addEventListener('click', () => {
                    document.querySelector('.ck-evaluation-badge') ? document.querySelector('.ck-evaluation-badge').remove() : '';
                    document.querySelector('.ck-powered-by') ? document.querySelector('.ck-powered-by').remove() : '';
                });
                if (document.querySelector('.ck-evaluation-badge')) {
                    document.querySelector('.ck-evaluation-badge').remove();
                }
                if (document.querySelector('.ck-powered-by')) {
                    document.querySelector('.ck-powered-by').remove();
                }
            }, 2000);
        });
    </script>

    <script src="https://cdn.ckeditor.com/ckeditor5/45.2.0/ckeditor5.umd.js" crossorigin></script>
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
@endpush
