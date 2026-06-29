@extends('admin.layout.layout')

@section('title', 'Transfer Domains - Step 2')

@push('style')
    <style>
        .category-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            transition: all 0.2s ease;
            background-color: white;
            cursor: pointer;
        }

        .category-card:hover {
            border-color: var(--primary-color, #f97316);
        }

        .category-card.selected {
            border-color: #10b981;
            background-color: #f0fdf4;
        }

        .domain-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #f3f4f6;
            color: #374151;
            border-radius: 4px;
            font-size: 12px;
            margin: 2px;
            border: 1px solid #e5e7eb;
        }

        .create-category-section {
            background-color: #f9fafb;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .create-category-section.active {
            border-color: var(--primary-color, #f97316);
            background-color: #fff7ed;
        }

        #createCategoryForm {
            display: none;
        }

        #createCategoryForm.active {
            display: block;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 24px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            background-color: var(--primary-color, #f97316);
            color: white;
        }

        .step-circle.inactive {
            background-color: #e5e7eb;
            color: #6b7280;
        }

        .step-circle.complete {
            background-color: #10b981;
        }
    </style>
@endpush

@section('main-content')
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Transfer Domains</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.pending-domains.index') }}" class="breadcrumb-link">Pending Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Step 2: Choose Category</a>
                    </div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end items-center gap-2">
                <a href="{{ route('admin.transfer-domains.step1') }}"
                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded">
                    Back to Step 1
                </a>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-col gap-2">
        @if (session('error'))
            <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                <span class="font-medium">{{ session('error') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="step-indicator !mt-4">
        <div class="flex items-center gap-2">
            <div class="step-circle complete">✓</div>
            <span class="text-sm font-semibold text-gray-800">Select Domains</span>
        </div>
        <span class="text-gray-400">→</span>
        <div class="flex items-center gap-2">
            <div class="step-circle">2</div>
            <span class="text-sm font-semibold text-gray-800">Choose Category</span>
        </div>
    </div>

    <div class="w-full content-card !mt-4 !mb-4">
        <h3 class="text-base font-semibold text-gray-800 !mb-2">
            Selected Domains ({{ $selectedCount }})
        </h3>
        <p class="text-sm text-gray-600">
            {{ $selectedCount }} domain(s) will be moved to your active inventory after you choose a category.
        </p>
    </div>

    <div class="w-full content-card">
        <form id="transferForm" action="{{ route('admin.transfer-domains.process') }}" method="POST">
            @csrf

            <input type="hidden" name="domain_ids_payload" value="{{ implode(',', $domainIds) }}">

            <h3 class="text-base font-semibold text-gray-800 !mb-4">Select Domain Category</h3>

            @if ($categories->isEmpty())
                <div class="text-center !py-6 !mb-4">
                    <p class="text-gray-500 text-sm">No categories found. Create your first category below.</p>
                </div>
            @endif

            <div id="categoryList" class="flex flex-col gap-3 !mb-4">
                @foreach ($categories as $category)
                    <div class="category-card" onclick="selectCategory({{ $category->id }}, this)">
                        <div class="flex items-center gap-4">
                            <input type="radio"
                                name="domain_category_id"
                                value="{{ $category->id }}"
                                class="category-radio w-4 h-4 cursor-pointer"
                                id="category_{{ $category->id }}">

                            <div class="flex-grow">
                                <h4 class="text-sm font-semibold text-gray-800">{{ $category->name }}</h4>
                                @if ($category->description)
                                    <p class="text-xs text-gray-600 !mt-1">{{ $category->description }}</p>
                                @endif
                                <p class="text-xs text-gray-500 !mt-1">
                                    {{ $category->domains_count }} domain(s) in this category
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="create-category-section" id="createCategorySection">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                    <h4 class="text-sm font-semibold text-gray-800">Create New Category</h4>
                    <button type="button"
                        onclick="toggleCreateForm()"
                        class="flex !p-2 !py-2 text-sm font-normal justify-center duration-300 bg-black hover:bg-[var(--primary-color)] text-white rounded">
                        + New Category
                    </button>
                </div>

                <div id="createCategoryForm" class="!mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label for="newCategoryName" class="text-sm font-medium text-gray-700">
                                Category Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                id="newCategoryName"
                                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600"
                                placeholder="e.g., High Authority Domains"
                                maxlength="100">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="newCategoryDescription" class="text-sm font-medium text-gray-700">
                                Description (Optional)
                            </label>
                            <input type="text"
                                id="newCategoryDescription"
                                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600"
                                placeholder="Brief description">
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 !mt-4">
                        <button type="button"
                            id="createCategoryBtn"
                            onclick="createCategory(this)"
                            class="flex !p-2 !py-2 text-sm font-normal justify-center duration-300 bg-green-600 hover:bg-green-700 text-white rounded">
                            Create & Select
                        </button>
                        <button type="button"
                            onclick="toggleCreateForm()"
                            class="flex !p-2 !py-2 text-sm font-normal justify-center duration-300 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded">
                            Cancel
                        </button>
                    </div>
                    <p id="createCategoryError" class="text-red-600 text-xs !mt-2 hidden"></p>
                </div>
            </div>

            <div class="!mt-6 flex flex-col sm:flex-row sm:justify-between gap-3">
                <a href="{{ route('admin.transfer-domains.step1') }}"
                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded">
                    ← Back to Step 1
                </a>
                <button type="submit"
                    id="transferBtn"
                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 bg-green-600 hover:bg-green-700 text-white rounded disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled>
                    Complete Transfer
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        function selectCategory(categoryId, card) {
            document.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            document.getElementById('category_' + categoryId).checked = true;
            document.getElementById('transferBtn').disabled = false;
            document.getElementById('createCategoryForm').classList.remove('active');
            document.getElementById('createCategorySection').classList.remove('active');
        }

        function toggleCreateForm() {
            const form = document.getElementById('createCategoryForm');
            const section = document.getElementById('createCategorySection');
            form.classList.toggle('active');
            section.classList.toggle('active');
            if (form.classList.contains('active')) {
                document.getElementById('newCategoryName').focus();
            }
        }

        function createCategory(btn) {
            const name = document.getElementById('newCategoryName').value.trim();
            const description = document.getElementById('newCategoryDescription').value.trim();
            const errorEl = document.getElementById('createCategoryError');

            errorEl.classList.add('hidden');
            errorEl.textContent = '';

            if (!name) {
                errorEl.textContent = 'Please enter a category name';
                errorEl.classList.remove('hidden');
                return;
            }

            const originalText = btn.innerHTML;
            btn.innerHTML = 'Creating...';
            btn.disabled = true;

            fetch('{{ route('admin.transfer-domains.create-category') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ name, description })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let categoryList = document.getElementById('categoryList');
                    const newCard = document.createElement('div');
                    newCard.className = 'category-card selected';
                    newCard.onclick = function() { selectCategory(data.category.id, this); };
                    newCard.innerHTML = `
                        <div class="flex items-center gap-4">
                            <input type="radio"
                                name="domain_category_id"
                                value="${data.category.id}"
                                class="category-radio w-4 h-4 cursor-pointer"
                                id="category_${data.category.id}"
                                checked>
                            <div class="flex-grow">
                                <h4 class="text-sm font-semibold text-gray-800">
                                    ${data.category.name}
                                    <span class="!ml-2 !px-2 !py-0.5 bg-green-100 text-green-800 text-xs rounded-full">New</span>
                                </h4>
                                ${data.category.description ? `<p class="text-xs text-gray-600 !mt-1">${data.category.description}</p>` : ''}
                                <p class="text-xs text-gray-500 !mt-1">0 domain(s) in this category</p>
                            </div>
                        </div>
                    `;

                    document.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
                    categoryList.appendChild(newCard);
                    document.getElementById('transferBtn').disabled = false;

                    document.getElementById('newCategoryName').value = '';
                    document.getElementById('newCategoryDescription').value = '';
                    toggleCreateForm();
                } else {
                    errorEl.textContent = data.message || 'Failed to create category';
                    errorEl.classList.remove('hidden');
                }
            })
            .catch(() => {
                errorEl.textContent = 'An error occurred while creating the category';
                errorEl.classList.remove('hidden');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        document.getElementById('transferForm').addEventListener('submit', function(e) {
            if (!document.querySelector('input[name="domain_category_id"]:checked')) {
                e.preventDefault();
                alert('Please select a category or create a new one');
            }
        });
    </script>
@endpush
