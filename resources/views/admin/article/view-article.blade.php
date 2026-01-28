@extends('admin.layout.layout')

@section('title', $article->name)

@push('style')
    <style>
        #article-container .ck-editor__editable_inline {
            min-height: 300px !important;
            /* increase as needed */
        }
    </style>
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/45.2.0/ckeditor5.css" crossorigin>
@endpush



@section('main-content')

    {{-- bread-crumbs --}}
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
                        <a href="{{ route('admin.article.index') }}" class="breadcrumb-link">Articles</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.article.show', $article->id) }}"
                            class="breadcrumb-link">{{ $article->name }}</a>

                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                {{-- <a href="" class="flex !p-2 !py-3 text-[16px] font-normal w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">+ Add Article</a> --}}
            </div>
        </div>
    </div>



    <div class="w-full flex flex-wrap justify-between items-start">
        <div class="flex flex-col gap-2 w-full content-card justify-start">
            <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
                {{ $article->name }}
            </h2>

            @if ($article->status == '0')
                <h2 class="text-lg capitalize !mb-4 bg-green-100 text-green-600 w-fit !p-2 rounded">
                  non-used
                </h2>
            @elseif ($article->status == '1')
                <h2 class="text-lg capitalize !mb-4 bg-red-100 text-red-600 w-fit !p-2 rounded">
                    used
                </h2>
            @endif


            <div class="w-full flex flex-col gap-3 p-2 !mt-4">
                <label for="language"
                    class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Language
                </label>
                <input type="text" name="language" value="{{ $article->language->name ?? 'English' }}" id="language"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600 "
                    disabled>

            </div>

            <div class="w-full flex flex-col gap-3 p-2 !mt-4">
                <label for="category"
                    class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Category
                </label>
                <input type="text" name="category" value="{{ $article->category->name ?? 'default' }}" id="category"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600"
                    disabled>

            </div>


            <div class="w-full flex flex-col gap-3 p-2 !mt-4">
                <label for="title"
                    class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                </label>
                <input type="text" name="title" value="{{ $article->name }}" placeholder="Add category title here"
                    id="title"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600"
                    disabled>

            </div>

            <div class="w-full flex flex-col gap-3 p-2 !mt-4" id="article-container">
                <label for="category_title"
                    class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Description
                </label>
                <div class="w-full flex-col gap-3 flex !p-8 ck-content bg-gray-50 rounded border border-gray-200">
                    {!! $article->description !!}
                </div>

            </div>

        </div>

    </div>



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
                console.log(document.querySelector('.ck-content'))
                document.querySelector('.ck-content').addEventListener('click', () => {
                    document.querySelector('.ck-evaluation-badge') ? document.querySelector(
                        '.ck-evaluation-badge').remove() : '';
                    document.querySelector('.ck-powered-by') ? document.querySelector(
                        '.ck-powered-by').remove() : '';

                })
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
