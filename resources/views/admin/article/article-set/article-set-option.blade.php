@extends('admin.layout.layout')

@section('title', 'Pick Your Article Creation Option')

@push('style')
    <style>
        #article-container .ck-editor__editable_inline {
            min-height: 300px !important;
            /* increase as needed */
        }

        .ck-toolbar_grouping {
            background: #f3f4f6 !important;
            border-radius: 4px 4px 0px 0px !important;
        }

        .ck-content {
            background: #fff !important;
            /* font-size: 14px !important; */
            border-radius: 0px 0px 4px 4px !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/45.2.0/ckeditor5.css" crossorigin>
    <link rel="stylesheet" href="{{ asset('ckeditor/ckeditor5/ckeditor5.css') }}">
@endpush
@section('main-content')


    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-full flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    {{-- {{ route('article-set') }} --}}
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Article Set</a>
                        <span>›</span>
                    </div>
                    {{-- {{ route('opt') }} --}}
                    <div class="breadcrumb-item">
                        <a href="" class="breadcrumb-link">Articles Options</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1 !px-1">
            @if (session()->has('cus__success'))
                <div class="w-full content-card">
                    <div class="!p-4  text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium"> {!! session('cus__success') !!}</span>
                    </div>
                </div>
            @endif
            @if (session()->has('cus__error'))
                <div class="w-full content-card">
                    <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        <span class="font-medium"> {!! session('cus__error') !!}</span>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- <pre>{{ (string) request('type') ? 'present' : 'absent' }}</pre> --}}

    <div class="w-full content-card">
        <h2 class="text-xl font-semibold">
            Create Content: Choose Your Starting Option below
        </h2>
        <div class="w-full flex flex-wrap gap-10 !mt-10">
            <label for="article_opt_1"
                class=" select-label flex flex-col gap-3 group items-center relative overflow-hidden justify-center bg-gray-50 border w-[225px] border-gray-200 mt-4 rounded-lg !p-6 cursor-pointer duration-300 transition-all hover:border-[var(--primary-color)]">
                <input type="radio" name="article_opt" class="article_set_radio_opt" id="article_opt_1"
                    value='ai-article-box' hidden>
                <img src="{{ asset('images/ai.png') }}" alt="" srcset="">
                <p
                    class="text-sm bg-[var(--primary-color)] text-white !p-2 rounded absolute top-full left-1/2 -translate-x-1/2 duration-300 opacity-0 group-hover:top-1/2  group-hover:-translate-y-1/2 group-hover:opacity-100 whitespace-nowrap ">
                    using Airtificial intelligence</p>
                <span
                    class="w-7 h-7 flex items-center justify-center bg-[var(--primary-color)] text-white rounded-full absolute left-1 duration-300 transition-all top-1 opacity-0 group-hover:opacity-100 check-span">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width='18' stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>

                </span>
            </label>
            <label for="article_opt_2"
                class="select-label flex flex-col gap-3 items-center group relative overflow-hidden justify-center bg-gray-50 border w-[225px] border-gray-200 mt-4 rounded-lg !p-6 cursor-pointer duration-300 transition-all hover:border-[var(--primary-color)]">
                <input type="radio" name="article_opt" class="article_set_radio_opt" id="article_opt_2"
                    value="manual-article-box" hidden>
                <img src="{{ asset('images/manual.png') }}" alt="" srcset="">
                <p
                    class="text-sm bg-[var(--primary-color)] text-white !p-2 rounded absolute top-full left-1/2 -translate-x-1/2 duration-300 opacity-0 group-hover:top-1/2  group-hover:-translate-y-1/2 group-hover:opacity-100 whitespace-nowrap ">
                    manual creation</p>
                <span
                    class="w-7 h-7 flex items-center justify-center bg-[var(--primary-color)] text-white rounded-full absolute left-1 duration-300 transition-all top-1 opacity-0 group-hover:opacity-100 check-span">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width='18' stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>

                </span>
            </label>
            <label for="article_opt_3"
                class=" select-label flex flex-col gap-3 items-center justify-center group relative overflow-hidden bg-gray-50 border w-[225px] border-gray-200 mt-4 rounded-lg !p-6 cursor-pointer duration-300 transition-all hover:border-[var(--primary-color)]">
                <input type="radio" name="article_opt" class="article_set_radio_opt" fileType='pdf' id="article_opt_3"
                    value="files-article-box" hidden>
                <img src="{{ asset('images/bulk-1.png') }}" alt="" srcset="">
                <p
                    class="text-sm bg-[var(--primary-color)] text-white !p-2 rounded absolute top-full left-1/2 -translate-x-1/2 duration-300 opacity-0 group-hover:top-1/2  group-hover:-translate-y-1/2 group-hover:opacity-100 whitespace-nowrap ">
                    Bulk upload Article Pdfs</p>
                <span
                    class="w-7 h-7 flex items-center justify-center bg-[var(--primary-color)] text-white rounded-full absolute left-1 duration-300 transition-all top-1 opacity-0 group-hover:opacity-100 check-span">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width='18' stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>

                </span>
            </label>
        </div>

    </div>

    <div class="w-full content-card">
        <h2 class="text-xl font-semibold flex gap-2 items-center">
            {{ $articleSet->name }}<span
                class="flex w-6 h-6 bg-[var(--primary-color)] text-white !text-sm rounded items-center justify-center">{{ count($articles) }}</span>
        </h2>

        <div class="flex flex-wrap overflow-x-auto !mt-6">
            <table class="w-full border border-gray-200 border-collapse text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-100">
                        @php
                            $tHead = ['sno', 'title', 'Action'];
                        @endphp
                        @foreach ($tHead as $t)
                            <th class="border border-gray-200   font-sans !font-normal !px-2 !py-3 capitilize text-left">
                                {{ $t }}
                            </th>
                        @endforeach
                    </tr>


                </thead>
                <tbody>
                    @foreach ($articles as $index => $article)
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 font-sans !px-3 !py-2">{{ $index + 1 }}
                            </td>
                            <td class="border border-gray-200 font-sans !px-2 !py-2">
                                {{ $article->name }}</td>
                            {{-- {{ route('edit-articles',['id'=>9]) }} --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-2">
                                <div class="flex flex-wrap gap-2 justify-center">
                                    <a href="{{ route('admin.article.edit', $article->id) }}"
                                        class="bg-green-500 flex items-center justify-center rounded text-white !px-3 !py-1 gap-1 duration-500">
                                        <span class="material-symbols-outlined !text-sm text-white">
                                            edit_square
                                        </span>
                                        Edit
                                    </a>

                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>




@endsection


@section('popup')


    <div
        class="w-screen min-h-screen flex justify-center items-start fixed top-0 left-0 z-1100 overflow-x-hidden overflow-y-auto {{ !request('type') ? 'hidden' : '' }} set-parent-div">

        <div
            class="set-overlay w-screen h-screen bg-black {{ !request('type') ? 'opacity-0 hidden' : 'opacity-30' }}  absolute top-0 left-0 duration-300 transition-all">
        </div>
        {{-- xxxxxxxxxxxxxxxxxxxxxxxxx manual article creation popup xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}

        <form action="{{ route('admin.articles.set.add.article') }}" method="post"
            class="w-1/2 flex flex-col items-center max-h-[80vh] overflow-y-auto overflow-x-hidden !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-[70px] {{ request('type') != 'manual' ? '-translate-y-[20%] opacity-0 hidden' : '' }}  linear duration-600 transition-all  option-box"
            id="manual-article-box">
            @csrf
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3">
                <h6>Create Article</h6>
                <button type="button" class="cursor-pointer close-pop" id='manual-article-set-close'>
                    <span class="material-symbols-outlined">
                        close
                    </span>
                </button>
            </div>
            @if (session()->has('manual_success'))
                <div class="w-full !p-4">
                    <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium">{{ session('manual_success') }}</span>
                    </div>
                </div>
            @endif
            <div class="w-full flex flex-col gap-1 !p-4">
                <div class="w-full flex flex-col gap-3 p-2">
                    <label for="name"
                        class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                    </label>
                    <input type="text" name="name" placeholder="Add category title here" id="article_title"
                        class="bg-white border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                    @error('name')
                        <p class="text-red-400 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                    @enderror

                </div>
                <div class="w-full flex flex-col gap-3 p-2 !mt-3" id="article-container">
                    <label for="editor"
                        class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Description
                    </label>
                    <textarea name="description" id="editor" rows="20"></textarea>
                    @error('description')
                        <p class="text-red-400 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                    @enderror
                </div>
                <div class="w-full flex flex-col gap-3 p-2 !mt-3 items-start" id="article-container">
                    {{-- article language we using article set  --}}
                    <input type="hidden" name="language" value="{{ $articleSet->article_language_id }}">
                    {{-- 0 represents manual --}}
                    <input type="hidden" name="type" value="0">
                    {{-- sending id of the page --}}
                    <input type="hidden" name="id" value="{{ request('id') }}">
                    <button type="submit" id="add-set-articles"
                        class="flex !p-2 !py-3 text-[16px] flex items-center gap-1 font-normal cursor-pointer duration-300 transition-all justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded">
                        <span class="material-symbols-outlined">
                            post_add
                        </span>
                        Add article
                    </button>

                </div>
            </div>
        </form>

        {{-- xxxxxxxxxxxxxxxxxxxxxxxxx Ai article creation popup xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}

        <div class="w-1/2 flex flex-col items-center max-h-[80vh] overflow-auto overflow-x-hidden !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-[70px] -translate-y-[20%] linear opacity-0 duration-600 transition-all hidden option-box"
            id="ai-article-box">
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3">
                <h6>Creat Content through Ai</h6>
                <button type="button" class="cursor-pointer close-pop" id='article-set-close'>
                    <span class="material-symbols-outlined">
                        close
                    </span>
                </button>
            </div>
            <div class="w-full flex !p-4">
                <span class="flex !p-2 text-sm rounded-full bg-[var(--primary-color)] text-white">Coming soon</span>
            </div>
        </div>

        {{-- xxxxxxxxxxxxxxxxxxxxxxxxx Files article creation popup xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}

        {{-- {{ route('admin.articles.import') }} --}}

        <form action="{{ route('admin.articles.set.import.articles') }}" method="POST" enctype="multipart/form-data"
            id="files-article-box"
            class="w-1/2 !p-4 flex flex-col max-h-[80vh] overflow-auto overflow-x-hidden !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-[70px]  {{ request('type') != 'file' ? '-translate-y-[20%] opacity-0 hidden' : '' }} linear duration-600 transition-all option-box">

            @csrf

            <h2 class="text-xl !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
                Bulk Upload Articles (DOCX)
            </h2>

            @if (session()->has('bulk__success'))
                <div class="w-full !mb-2">
                    <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium">{{ session('bulk__success') }}</span>
                    </div>
                </div>
            @endif

            @if (session()->has('bulk__error'))
                <div class="w-full !mb-2">
                    <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        <span class="font-medium">{{ session('bulk__error') }}</span>
                    </div>
                </div>
            @endif

            {{-- Drop Zone --}}
            <label for="docxFile" id="docxDropZone"
                class="flex flex-col items-center justify-center w-full h-56
               border-2 border-dashed border-gray-300 rounded-lg
               cursor-pointer bg-gray-50 hover:bg-gray-100
               transition">

                <svg class="w-10 h-10 !mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16V4a1 1 0 011-1h8a1 1 0 011 1v12m-5 4h.01M12 20h.01" />
                </svg>

                <p class="!mb-1 text-sm text-gray-600">
                    <span class="font-semibold">Click to upload</span> or drag & drop
                </p>

                <p class="text-xs text-gray-500">DOCX only (Max 20MB)</p>

                <input id="docxFile" type="file" name="docx" accept=".docx" class="hidden" />
            </label>

            {{-- File Info --}}
            <div id="docxFileInfo" class="hidden !mt-4 !p-3 bg-gray-100 rounded text-sm">
                <p><strong>File:</strong> <span id="docxFileName"></span></p>
                <p><strong>Size:</strong> <span id="docxFileSize"></span></p>
            </div>
            {{-- data creating/uploading format  --}}
            <input type="hidden" name="type" value="1">
            {{-- Submit --}}

            {{-- article language we using article set  --}}
            <input type="hidden" name="language" value="{{ $articleSet->article_language_id }}">
            {{-- 0 represents manual --}}
            <input type="hidden" name="type" value="1">
            {{-- sending id of the page --}}
            <input type="hidden" name="id" value="{{ request('id') }}">

            <button type="submit"
                class="!mt-4 w-full !p-3 bg-black text-white rounded
               hover:bg-[var(--primary-color)]">
                Import DOCX
            </button>

        </form>


        <div class="w-screen h-screen flex items-center justify-center z-10 hidden" id="customize_loader">
            <div class="w-12 h-12 border-6 border-white border-t-[var(--primary-color)] rounded-full animate-spin"></div>
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

    {{-- for uploading the articles through docx file --}}

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            const dropZone = document.getElementById('docxDropZone');
            const fileInput = document.getElementById('docxFile');
            const infoBox = document.getElementById('docxFileInfo');
            const fileName = document.getElementById('docxFileName');
            const fileSize = document.getElementById('docxFileSize');

            // Prevent default drag behavior
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(event => {
                dropZone.addEventListener(event, e => {
                    e.preventDefault();
                    e.stopPropagation();
                });
            });

            // Highlight on drag
            dropZone.addEventListener('dragover', () => {
                dropZone.classList.add('border-orange-500', 'bg-orange-50');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('border-orange-500', 'bg-orange-50');
            });

            // Handle drop
            dropZone.addEventListener('drop', e => {
                const files = e.dataTransfer.files;
                if (files.length) {
                    fileInput.files = files;
                    showFile(files[0]);
                }
                dropZone.classList.remove('border-orange-500', 'bg-orange-50');
            });

            // Handle click select
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) {
                    showFile(fileInput.files[0]);
                }
            });

            function showFile(file) {
                if (!file.name.endsWith('.docx')) {
                    alert('Only DOCX files are allowed');
                    fileInput.value = '';
                    return;
                }

                infoBox.classList.remove('hidden');
                fileName.textContent = file.name;
                fileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            }
        });
    </script>


    <script src="https://cdn.ckeditor.com/ckeditor5/45.2.0/ckeditor5.umd.js" crossorigin></script>

    <script type="module" src="{{ asset('js/general.js') }}"></script>
    {{-- <script src="{{ asset('js/article-set/article-sets-opts.js') }}"></script> --}}
@endpush
