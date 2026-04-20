@extends('admin.layout.layout')

@section('title', 'Users Management')

@php
    $currentAdmin = Auth::guard('admin')->user();
@endphp

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 md:flex-row md:items-start md:justify-between md:gap-4">
            <div class="min-w-0 flex-1">
                <h2 class="page-title">Users Management</h2>
                <div class="breadcrumb flex-wrap gap-y-1">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Users</a>
                    </div>
                </div>
            </div>
            @if($currentAdmin->isSuperAdmin())
            <div class="w-full md:w-auto flex justify-start md:justify-end items-center shrink-0">
                <a href="{{ route('admin.user.create') }}" id="make__admin__user"
                    class="inline-flex !p-2 !py-3 text-[16px] font-normal w-full md:w-auto min-w-[120px] justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded whitespace-nowrap">
                    Add User
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('cus__success'))
        <div class="bg-green-100 border border-green-400 text-green-700 !px-4 !py-3 rounded !mb-4 flex items-center justify-between">
            <span>{{ session('cus__success') }}</span>
            <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">&times;</button>
        </div>
    @endif
    @if(session('cus__error'))
        <div class="bg-red-100 border border-red-400 text-red-700 !px-4 !py-3 rounded !mb-4 flex items-center justify-between">
            <span>{{ session('cus__error') }}</span>
            <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">&times;</button>
        </div>
    @endif

    <div class="w-full flex flex-wrap gap-8 justify-center">
        <div class="w-full flex flex-wrap gap-4 justify-center">
            <div class="w-full mx-auto content-card">
                <div class="px-6 pt-6 flex flex-col gap-3 justify-between">
                    {{-- heading here --}}
                    <h2 class="text-xl bg-[var(--primary-color)] text-white !p-2 rounded font-semibold capitalize w-fit">
                        Admin & Members Account 
                        <span class="material-symbols-outlined !text-sm">arrow_cool_down</span>
                    </h2>

                    {{-- table code here --}}
                    <div class="overflow-x-auto !mt-6 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                        <table class="w-full min-w-[900px] border border-gray-200 border-collapse text-sm whitespace-nowrap">
                            <thead>
                                <tr class="bg-[var(--sidebar-bg)] text-white active-border-color">
                                    @php
                                        $tHead = ['S.No', 'Name', 'Email', 'Role', 'Created At', 'Action'];
                                    @endphp
                                    @foreach ($tHead as $t)
                                        <th class="border border-gray-50 font-sans !font-normal !px-3 !py-4 capitalize text-left">
                                            {{ $t }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $index => $user)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 font-sans !px-3 !py-3">
                                            {{ $paginate ? ($users->currentPage() - 1) * $users->perPage() + $index + 1 : $index + 1 }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-3 !py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full bg-[var(--primary-color)] text-white flex items-center justify-center text-xs font-bold">
                                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                                </div>
                                                {{ $user->name }}
                                            </div>
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-3 !py-3">{{ $user->email }}</td>
                                        <td class="border border-gray-200 font-sans !px-3 !py-3">
                                            @php
                                                $roleColors = [
                                                    0 => 'bg-green-100 text-green-700',
                                                    1 => 'bg-blue-100 text-blue-700',
                                                    2 => 'bg-purple-100 text-purple-700',
                                                ];
                                                $roleColor = $roleColors[$user->type] ?? 'bg-gray-100 text-gray-700';
                                            @endphp
                                            <span class="!px-3 !py-2 rounded-full text-xs font-medium {{ $roleColor }}">
                                                {{ $user->getRoleName() }}
                                            </span>
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-3 !py-3">
                                            {{ $user->created_at->format('d M Y, h:i A') }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-3 !py-3 min-w-[120px]">
                                            <div class="flex items-center justify-center gap-2 flex-nowrap">
                                                {{-- View Button --}}
                                                <a href="{{ route('admin.user.show', $user->id) }}"
                                                    class="bg-green-600 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-green-700"
                                                    title="View User">
                                                    <span class="material-symbols-outlined !text-[16px] text-white">visibility</span>
                                                </a>
                                                
                                                {{-- Edit Button (Only Super Admin or self) --}}
                                                @if($currentAdmin->isSuperAdmin() || $currentAdmin->id === $user->id)
                                                    @if(!$user->isSuperAdmin() || $currentAdmin->id === $user->id)
                                                    <a href="{{ route('admin.user.edit', $user->id) }}"
                                                        class="bg-yellow-500 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-yellow-600"
                                                        title="Edit User">
                                                        <span class="material-symbols-outlined !text-[16px] text-white">edit_square</span>
                                                    </a>
                                                    @endif
                                                @endif
                                                
                                                {{-- Delete Button (Only Super Admin, cannot delete self or other Super Admins) --}}
                                                @if($currentAdmin->isSuperAdmin() && $currentAdmin->id !== $user->id && !$user->isSuperAdmin())
                                                    <button type="button"
                                                        onclick="confirmDelete({{ $user->id }}, '{{ $user->name }}')"
                                                        class="bg-red-600 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-red-700"
                                                        title="Delete User">
                                                        <span class="material-symbols-outlined !text-[16px] text-white">delete</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="border border-gray-200 font-sans !px-3 !py-8 text-center text-gray-500">
                                            No users found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    <div class="w-full flex flex-col mt-4">
                        @if ($paginate)
                            {{ $users->links() }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
        <div class="bg-white rounded-lg !p-6 max-w-md w-full mx-4">
            <div class="flex items-center gap-3 !mb-4">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-600">warning</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Delete User</h3>
                    <p class="text-sm text-gray-500">This action cannot be undone</p>
                </div>
            </div>
            
            <p class="text-gray-600 !mb-4">
                Are you sure you want to delete <strong id="deleteUserName"></strong>?
            </p>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg !p-3 !mb-4">
                <p class="text-sm text-yellow-800">
                    <strong>Note:</strong> All data (articles, domains, campaigns, etc.) belonging to this user will be transferred to your account.
                </p>
            </div>
            
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="closeDeleteModal()" 
                    class="!px-4 !py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
                    Cancel
                </button>
                <form id="deleteForm" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                        class="!px-4 !py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                        Delete User
                    </button>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function confirmDelete(userId, userName) {
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteForm').action = `/admin/user/${userId}`;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

// Close modal on backdrop click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>
@endpush
