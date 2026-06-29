@extends('admin.layout.layout')

@section('title', 'Domain Categories')

@push('style')
    <style>
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu-animate {
            animation: slideDown 0.2s ease-out;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            padding: 0.35rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #c2410c;
            background-color: rgba(255, 74, 23, 0.12);
            border: 1px solid rgba(255, 74, 23, 0.25);
            border-radius: 9999px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-heading h3 {
            font-size: 1.0625rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }

        .theme-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 44px;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            color: #fff;
            border-radius: 0.25rem;
            background-color: var(--primary-color);
            transition: background-color 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .theme-btn:hover {
            background-color: #e0410f;
        }

        .form-label {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .form-label.required::after {
            content: '*';
            margin-left: 0.25rem;
            color: var(--primary-color);
        }

        .form-input {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 0.75rem;
            font-size: 0.875rem;
            width: 100%;
            border-radius: 0.25rem;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .form-input:focus {
            border-color: var(--primary-color);
        }

        .action-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 9999px;
            transition: background-color 0.2s ease;
        }
    </style>
@endpush

@section('main-content')

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Domain Categories</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Category</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-col gap-2">
        @if (session()->has('cus__success'))
            <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                <span class="font-medium">{{ session('cus__success') }}</span>
            </div>
        @endif
        @if (session()->has('cus__error'))
            <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                <span class="font-medium">{{ session('cus__error') }}</span>
            </div>
        @endif
    </div>

    {{-- Add category form --}}
    <div class="w-full content-card !mt-4">
        <div class="section-heading">
            <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">create_new_folder</span>
            <h3>Add Domain Category</h3>
        </div>

        <form action="{{ route('admin.domain.category.store') }}" method="post" class="w-full">
            @csrf
            <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="flex flex-col gap-2">
                    <label for="category_title" class="form-label required">Title</label>
                    <input type="text" name="name" placeholder="Add category title here" id="category_title"
                        value="{{ old('name') }}"
                        class="form-input">
                    @error('name')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label for="category_bio" class="form-label">Description</label>
                    <textarea name="description" id="category_bio" rows="3" placeholder="Category description here"
                        class="form-input resize-y min-h-[46px]">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="w-full flex justify-end !mt-5 !pt-4 border-t border-gray-100">
                <button type="submit" class="theme-btn">
                    <span class="material-symbols-outlined !text-base">add</span>
                    Add Category
                </button>
            </div>
        </form>
    </div>

    {{-- Categories table --}}
    <div class="w-full content-card min-w-0 !mt-4">
        <div class="section-heading !mb-5">
            <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">folder_open</span>
            <h3>All Categories</h3>
        </div>

        <div class="w-full flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="w-full xl:w-auto xl:flex-1 flex flex-col gap-2 min-w-0">
                @error('actions')
                    <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                @enderror
                @error('bulk_ids')
                    <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                @enderror
                <form action="{{ route('admin.domain.category.delete') }}"
                    class="w-full flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2 xl:max-w-[560px]" method="post">
                    @csrf
                    <select name="actions"
                        class="form-input !py-3 sm:flex-1 sm:min-w-[10rem]">
                        <option value="">Bulk actions</option>
                        <option value="1">Delete</option>
                    </select>
                    <input type="hidden" name="bulk_ids" id="valHolders">
                    <button type="submit"
                        class="flex !p-3 !px-4 text-sm font-normal justify-center duration-300 transition-all bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white rounded cursor-pointer shrink-0 w-full sm:w-auto">
                        Apply
                    </button>
                </form>
            </div>
            <div class="w-full xl:w-auto xl:flex-1 flex justify-start xl:justify-end items-center gap-2 min-w-0">
                <div class="relative w-full sm:max-w-sm xl:w-[320px] max-h-12 overflow-hidden min-w-0">
                    <form method="GET" action="{{ url()->current() }}" class="relative w-full">
                        <input type="search" name="search" placeholder="Search categories..." id="search_category"
                            value="{{ request('search') }}"
                            class="form-input !pr-[50px] max-h-12 shadow-sm">
                        <button type="submit"
                            class="w-12 h-12 flex items-center justify-center bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] transition-colors absolute top-0 right-0 rounded-r">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto !mt-6 w-full max-w-full min-w-0">
            <table class="w-full min-w-[760px] border border-gray-200 border-collapse text-sm" id="domainCategoryTable">
                <thead>
                    <tr class="bg-[var(--sidebar-bg)] text-white">
                        <th class="border border-gray-200 !px-2 !py-3 w-10 text-center">
                            <input type="checkbox" name="bulk_category_select[]" id="bulk-checkBox-selector">
                        </th>
                        @php
                            $tHead = ['Sno', 'Category', 'User', 'Date', 'Action'];
                        @endphp
                        @foreach ($tHead as $t)
                            <th class="border border-gray-200 font-sans !font-normal !px-3 !py-3 text-left">
                                {{ $t }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $index => $category)
                        <tr class="hover:bg-gray-50 border-b border-gray-100">
                            <td class="border border-gray-200 !px-2 !py-2.5 text-center">
                                <input type="checkbox" name="bulk_category_select[]" class="multi-check"
                                    value="{{ $category->id }}">
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5 text-gray-600">
                                {{ $categories->firstItem() + $index }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <span class="category-badge" title="{{ $category->name }}">{{ $category->name }}</span>
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5 text-gray-700">
                                {{ $category->Admin?->name ?? '—' }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5 text-gray-600 whitespace-nowrap">
                                {{ $category->updated_at->format('m-d-Y') }}
                            </td>
                            <td class="border border-gray-200 !px-3 !py-2.5">
                                <div class="flex flex-wrap gap-2 justify-center">
                                    <a href="javascript:void(0)" data-row="{{ $index + 1 }}"
                                        data-id="{{ $category->id }}"
                                        class="action-icon-btn bg-yellow-500 hover:bg-yellow-600 upd-pop-btn">
                                        <span class="material-symbols-outlined !text-[16px] text-white">edit_square</span>
                                    </a>
                                    <form action="{{ route('admin.domain.category.destroy', $category->id) }}" method="post">
                                        @csrf
                                        @method('delete')
                                        <input type="hidden" name="del_id" value="{{ $category->id }}">
                                        <button type="submit"
                                            class="action-icon-btn bg-red-600 hover:bg-red-700 cursor-pointer">
                                            <span class="material-symbols-outlined !text-[16px] text-white">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="border border-gray-200 !px-4 !py-8 text-center text-gray-500">
                                No categories found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($categories->hasPages())
            <div class="!mt-4">
                {{ $categories->links() }}
            </div>
        @endif
    </div>

@endsection

@section('popup')

    <div
        class="w-screen h-screen flex justify-center items-start fixed top-0 left-0 z-1100 overflow-hidden hidden set-parent-div">
        <div
            class="set-overlay w-screen h-screen bg-black opacity-0 absolute top-0 left-0 hidden duration-300 transition-all">
        </div>
        <div class="pop-box w-[480px] flex flex-col hidden items-center !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-[70px] -translate-y-[20%] linear opacity-0 duration-600 transition-all"
            id="update-article-box">
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3">
                <h6 class="font-semibold text-gray-800">Update Category</h6>
                <button type="button" class="cursor-pointer article-set-close" id=''>
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="w-full !p-4 !pb-0 flex flex-col gap-2">
                <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full duration-500 transition-all opacity-0 hidden"
                    id="success-row" role="alert">
                    <span class="font-medium">Success!</span> <span class="message"></span>
                </div>
                <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full duration-500 transition-all opacity-0 hidden"
                    id="error-row" role="alert">
                    <span class="font-medium">Error!</span> <span class="message"></span>
                </div>
            </div>
            <div class="w-full flex flex-col gap-3 !p-3">
                <label for="upd_set_title" class="form-label required">Category Name</label>
                <input type="text" name="upd_set_title" placeholder="Enter category name" id="upd_set_title"
                    class="form-input">
                <input type="hidden" name="upd_category" id="upd_cat_id">
            </div>
            <div class="w-full flex justify-end items-center !bg-gray-200 !px-3 !py-2">
                <a href="javascript:void(0)" type="button" data-id=""
                    class="cursor-pointer upd-domain-category theme-btn !min-h-[40px]">
                    <div
                        class="w-3 h-3 border-2 border-gray-100 border-t-transparent rounded-full animate-spin loader hidden">
                    </div>
                    Update
                </a>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/article-set/create-set.js') }}"></script>
    <script type="module" src="{{ asset('js/bulk-select/script.js') }}"></script>
@endpush
