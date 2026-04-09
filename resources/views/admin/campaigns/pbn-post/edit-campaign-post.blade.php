@extends('admin.layout.layout')

@section('title', 'Edit Campaign Post - Admin Panel')

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
                        <a href="{{ route('admin.campaign.index') }}" class="breadcrumb-link">Campaigns</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.campaign.show', $campaign->id) }}"
                            class="breadcrumb-link">{{ $campaign->name }}</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="javascript:void(0)" class="breadcrumb-link">Edit Campaign Post</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                {{-- <a href="" class="flex !p-2 !py-3 text-[16px] font-normal w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">+ Add Article</a> --}}
            </div>
        </div>
    </div>

    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1">
            @if (session()->has('cus__success'))
                <div class="w-full content-card">
                    <div class="!p-4  text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium"> {{ session('cus__success') }}</span>
                    </div>
                </div>
            @endif
            @if (session()->has('cus__error'))
                <div class="w-full content-card">
                    <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        <span class="font-medium"> {{ session('cus__error') }}</span>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Keyword / URL: single or multiple rows (convert single link → multiple links) --}}
    <div class="w-full content-card !p-4 !mb-4 flex flex-col gap-3">
        <h2 class="text-lg font-medium bg-gray-800 text-white w-fit !px-3 !py-2 rounded">
            Keywords &amp; URLs (this post)
        </h2>
        <p class="text-sm text-gray-600 max-w-3xl">
            Started with one link? Use <strong>+ Add row</strong> to add more keyword/URL pairs for this post only.
            Saving updates the database and queues a remote content sync for published posts (same as campaign bulk edit).
            <strong>At least one</strong> complete keyword + URL pair is required.
        </p>
        <form action="{{ route('admin.campaign.update.post.keywords', $campaignPost->id) }}" method="POST" id="single-post-keyword-form" class="w-full flex flex-col gap-3">
            @csrf
            <div class="flex flex-col gap-3 batch-rows" data-batch-index="0">
                @foreach ($keywordPairs as $pair)
                    <div class="flex flex-wrap gap-2 items-center batch-row">
                        <input type="text" name="batch_keyword[]" value="{{ $pair['keyword'] }}"
                            class="flex-1 min-w-[120px] rounded !p-2 text-sm border border-gray-300 focus:border-[var(--primary-color)]"
                            placeholder="Keyword" autocomplete="off">
                        <input type="text" name="batch_url[]" value="{{ $pair['url'] }}"
                            class="flex-1 min-w-[180px] rounded !p-2 text-sm border border-gray-300 focus:border-[var(--primary-color)]"
                            placeholder="https://..." autocomplete="off">
                        <button type="button"
                            class="remove-pair-btn !px-2 !py-2 rounded bg-red-100 text-red-700 hover:bg-red-200 text-sm shrink-0">Remove</button>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <button type="button"
                    class="add-pair-btn !px-3 !py-1.5 rounded bg-gray-200 hover:bg-gray-300 text-sm">+ Add row</button>
                <button type="submit"
                    class="!px-4 !py-2 rounded-lg bg-[var(--primary-color)] text-white text-sm hover:opacity-90">
                    Save keywords &amp; URLs
                </button>
            </div>
        </form>
    </div>

    {{-- {{ route('admin.article.update', $article->id) }} --}}
    <form action="{{ route('admin.campaign.update.post',$campaignPost->id) }}" method="POST" class="w-full flex flex-col gap-2 content-card items-start">
        @csrf
        {{-- @method('PUT') --}}
        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">Edit
            {{ $fetchedData['post_title'] }}
        </h2>

        {{-- hidden data here --}}


   

        <div class="w-full flex flex-col gap-3 p-2">
            <label for="category_title"
                class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
            </label>
            <input type="text" name="name" value="{{ $fetchedData['post_title'] }}"
                placeholder="Add category title here" id="category_title"
                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

        </div>

        <div class="w-full flex flex-col gap-3 p-2 !mt-6" id="article-container">
            <label for="category_title"
                class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Description
            </label>
            <textarea name="description" id="update-editor" rows="20">{!! $fetchedData['post_content'] !!}</textarea>

        </div>
        <div class="w-full !mt-6">
            <button
                class="!p-3 text-[16px] cursor-pointer bg-black text-white rounded hover:bg-[var(--primary-color)] w-full">Update
                Article
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
            var kwForm = document.getElementById('single-post-keyword-form');
            if (kwForm) {
                kwForm.querySelectorAll('.remove-pair-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var row = this.closest('.batch-row');
                        var block = this.closest('.batch-rows');
                        if (block && block.querySelectorAll('.batch-row').length > 1) row.remove();
                    });
                });
                var addBtn = kwForm.querySelector('.add-pair-btn');
                var batchRows = kwForm.querySelector('.batch-rows');
                if (addBtn && batchRows) {
                    addBtn.addEventListener('click', function() {
                        var firstRow = batchRows.querySelector('.batch-row');
                        if (!firstRow) return;
                        var newRow = firstRow.cloneNode(true);
                        newRow.querySelectorAll('input').forEach(function(inp) { inp.value = ''; });
                        batchRows.appendChild(newRow);
                        newRow.querySelector('.remove-pair-btn').addEventListener('click', function() {
                            if (batchRows.querySelectorAll('.batch-row').length > 1) newRow.remove();
                        });
                    });
                }
            }

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
