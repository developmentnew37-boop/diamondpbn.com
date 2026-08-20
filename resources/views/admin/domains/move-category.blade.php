@extends('admin.layout.layout')

@section('title', 'Move Category')

@push('style')
    <style>
        .move-cat-card {
            max-width: 560px;
        }
        .move-cat-field {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }
        .move-cat-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }
        .move-cat-hint {
            font-size: 0.75rem;
            color: #6b7280;
        }
        .move-cat-select {
            width: 100%;
            min-height: 46px;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            color: #111827;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 0.25rem;
            outline: none;
        }
        .move-cat-select:focus {
            border-color: var(--primary-color);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
        }
        .move-cat-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            color: #fff;
            background: var(--primary-color);
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
        }
        .move-cat-btn:hover {
            background: #e0410f;
        }
        .move-cat-info {
            background-color: rgba(255, 74, 23, 0.08);
            border: 1px solid rgba(255, 74, 23, 0.25);
            border-radius: 0.25rem;
            padding: 1rem;
            font-size: 0.875rem;
            color: #374151;
        }
    </style>
@endpush

@section('main-content')
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Move Category</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.select.category') }}" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Move Category</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1 !px-1 !mb-3">
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
    @endif

    <div class="w-full content-card">
        <div class="px-6 pt-6 pb-8 flex flex-col gap-5 move-cat-card">
            <div class="move-cat-info">
                Select the <strong>old category</strong> (where domains are now) and the
                <strong>new category</strong> (where they should go). All domains in the old category
                will be moved to the new one.
            </div>

            <form method="POST" action="{{ route('admin.domain.move-category.process') }}" id="moveCategoryForm"
                class="flex flex-col gap-4">
                @csrf

                <div class="move-cat-field">
                    <label class="move-cat-label" for="source_category_id">Old category</label>
                    <select name="source_category_id" id="source_category_id" class="move-cat-select" required>
                        <option value="">Select old category</option>
                        @foreach ($domainCategories as $category)
                            <option value="{{ $category->id }}"
                                data-count="{{ (int) $category->domains_count }}"
                                {{ (string) old('source_category_id') === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }} ({{ (int) $category->domains_count }})
                            </option>
                        @endforeach
                    </select>
                    <p class="move-cat-hint" id="sourceHint">Domains currently in this category will be moved.</p>
                </div>

                <div class="move-cat-field">
                    <label class="move-cat-label" for="target_category_id">New category</label>
                    <select name="target_category_id" id="target_category_id" class="move-cat-select" required>
                        <option value="">Select new category</option>
                        @foreach ($domainCategories as $category)
                            <option value="{{ $category->id }}"
                                {{ (string) old('target_category_id') === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="move-cat-hint">This becomes the new category for those domains.</p>
                </div>

                @error('source_category_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('target_category_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div>
                    <button type="submit" class="move-cat-btn">Move domains</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('moveCategoryForm');
            const source = document.getElementById('source_category_id');
            const target = document.getElementById('target_category_id');
            const hint = document.getElementById('sourceHint');
            if (!form || !source || !target) return;

            function refreshHint() {
                const opt = source.options[source.selectedIndex];
                const count = opt ? parseInt(opt.dataset.count || '0', 10) : 0;
                const name = opt && opt.value ? opt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim() : '';
                hint.textContent = name
                    ? count + ' domain(s) in "' + name + '" will be moved.'
                    : 'Domains currently in this category will be moved.';
            }

            source.addEventListener('change', refreshHint);
            refreshHint();

            form.addEventListener('submit', function (e) {
                if (!source.value || !target.value) return;
                if (source.value === target.value) {
                    e.preventDefault();
                    alert('Old and new category must be different.');
                    return;
                }
                const opt = source.options[source.selectedIndex];
                const count = parseInt(opt.dataset.count || '0', 10);
                const fromName = opt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
                const toOpt = target.options[target.selectedIndex];
                const toName = toOpt.textContent.trim();
                if (!confirm('Move all ' + count + ' domain(s) from "' + fromName + '" to "' + toName + '"?')) {
                    e.preventDefault();
                }
            });
        })();
    </script>
@endpush
