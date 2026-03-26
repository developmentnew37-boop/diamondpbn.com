@extends('admin.layout.layout')

@section('title', ($isOwnProfile ? 'My Profile' : $profileAdmin->name . "'s Profile") . ' - Diamond PBN')

@section('main-content')
    {{-- Breadcrumb --}}
    <div class="page-header">
        <h1 class="page-title">{{ $isOwnProfile ? 'My Profile' : 'User Profile' }}</h1>
        <div class="breadcrumb">
            <div class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
            </div>
            <div class="breadcrumb-item">
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current">Profile</span>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('cus__success'))
        <div class="bg-green-100 border border-green-400 text-green-700 !px-4 !py-3 rounded !mb-4">
            {{ session('cus__success') }}
        </div>
    @endif
    @if(session('cus__error'))
        <div class="bg-red-100 border border-red-400 text-red-700 !px-4 !py-3 rounded !mb-4">
            {{ session('cus__error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Profile Card --}}
        <div class="lg:col-span-1">
            <div class="content-card">
                <div class="flex flex-col items-center text-center">
                    {{-- Avatar --}}
                    <div class="w-24 h-24 rounded-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white text-3xl font-bold !mb-4">
                        {{ strtoupper(substr($profileAdmin->name, 0, 2)) }}
                    </div>
                    
                    <h2 class="text-xl font-semibold text-gray-800">{{ $profileAdmin->name }}</h2>
                    <p class="text-sm text-gray-500 !mt-1">{{ $profileAdmin->email }}</p>
                    
                    {{-- Role Badge --}}
                    <div class="!mt-3">
                        @php
                            $roleColors = [
                                0 => 'bg-green-100 text-green-700 border-green-300',
                                1 => 'bg-blue-100 text-blue-700 border-blue-300',
                                2 => 'bg-purple-100 text-purple-700 border-purple-300',
                            ];
                            $roleColor = $roleColors[$profileAdmin->type] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                        @endphp
                        <span class="!px-3 !py-1 text-sm font-medium rounded-full border {{ $roleColor }}">
                            {{ $profileAdmin->getRoleName() }}
                        </span>
                    </div>
                    
                    {{-- Member Since --}}
                    <div class="!mt-4 text-sm text-gray-500">
                        <span class="material-symbols-outlined text-sm align-middle">calendar_today</span>
                        Member since {{ $profileAdmin->created_at->format('M d, Y') }}
                    </div>
                    
                    @if($canEdit && $isOwnProfile)
                        <button type="button" onclick="toggleEditForm()" 
                            class="!mt-4 bg-black text-white !px-4 !py-2 rounded text-sm hover:bg-gray-700 transition flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">edit</span>
                            Edit Profile
                        </button>
                    @endif
                </div>
                
                {{-- Quick Stats --}}
                <div class="!mt-6 !pt-6 border-t border-gray-200">
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div>
                            <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total_campaigns']) }}</p>
                            <p class="text-sm text-gray-500">Campaigns</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['articles']['total']) }}</p>
                            <p class="text-sm text-gray-500">Articles</p>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Edit Profile Form (Hidden by default) --}}
            @if($canEdit && $isOwnProfile)
            <div id="editProfileForm" class="content-card !mt-4 hidden">
                <h3 class="text-lg font-semibold !mb-4">Edit Profile</h3>
                <form action="{{ route('admin.profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 !mb-1">Name</label>
                            <input type="text" name="name" value="{{ old('name', $profileAdmin->name) }}" 
                                class="w-full !px-3 !py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            @error('name')
                                <p class="text-red-500 text-sm !mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 !mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email', $profileAdmin->email) }}" 
                                class="w-full !px-3 !py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            @error('email')
                                <p class="text-red-500 text-sm !mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <hr class="my-4">
                        <p class="text-sm text-gray-500">Leave password fields empty to keep current password</p>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 !mb-1">Current Password</label>
                            <input type="password" name="current_password" 
                                class="w-full !px-3 !py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            @error('current_password')
                                <p class="text-red-500 text-sm !mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 !mb-1">New Password</label>
                            <input type="password" name="new_password" 
                                class="w-full !px-3 !py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            @error('new_password')
                                <p class="text-red-500 text-sm !mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 !mb-1">Confirm New Password</label>
                            <input type="password" name="new_password_confirmation" 
                                class="w-full !px-3 !py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                        </div>
                        
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 bg-orange-500 text-white !px-4 !py-2 rounded hover:bg-orange-600 transition">
                                Save Changes
                            </button>
                            <button type="button" onclick="toggleEditForm()" class="flex-1 bg-gray-200 text-gray-700 !px-4 !py-2 rounded hover:bg-gray-300 transition">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endif
        </div>

        {{-- Statistics & Activity --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Statistics Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="content-card !p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-purple-600">article</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($stats['articles']['total']) }}</p>
                            <p class="text-xs text-gray-500">Total Articles</p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card !p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-orange-600">language</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($stats['domains']['total']) }}</p>
                            <p class="text-xs text-gray-500">Total Domains</p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card !p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600">campaign</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($stats['campaigns']['pbn_posts']) }}</p>
                            <p class="text-xs text-gray-500">PBN Campaigns</p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card !p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-green-600">view_sidebar</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($stats['campaigns']['sidebar']) }}</p>
                            <p class="text-xs text-gray-500">Sidebar Campaigns</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- User Creation Summary (By Period) --}}
            <div class="content-card">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 !mb-4">
                    <div>
                        <h3 class="text-lg font-semibold">User Created Data Summary</h3>
                        <p class="text-sm text-gray-500">{{ $creationSummary['range_label'] }}</p>
                    </div>

                    <form method="GET" action="" class="flex items-center gap-2">
                        <label for="range" class="text-sm text-gray-600">Period</label>
                        <select id="range" name="range" onchange="this.form.submit()"
                            class="!px-3 !py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="day" {{ $range === 'day' ? 'selected' : '' }}>1 Day</option>
                            <option value="week" {{ $range === 'week' ? 'selected' : '' }}>1 Week</option>
                            <option value="year" {{ $range === 'year' ? 'selected' : '' }}>1 Year</option>
                        </select>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border border-gray-200">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="text-left !px-4 !py-3 border">Module</th>
                                <th class="text-right !px-4 !py-3 border">Created Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($creationSummary['rows'] as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="!px-4 !py-3 border">{{ $row['label'] }}</td>
                                    <td class="!px-4 !py-3 border text-right font-semibold">{{ number_format($row['count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Campaign Breakdown --}}
            <div class="content-card">
                <h3 class="text-lg font-semibold !mb-4">Campaign Breakdown</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="text-center !p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-blue-600">{{ $stats['campaigns']['pbn_posts'] }}</p>
                        <p class="text-sm text-gray-600">PBN Posts</p>
                    </div>
                    <div class="text-center !p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-green-600">{{ $stats['campaigns']['sidebar'] }}</p>
                        <p class="text-sm text-gray-600">Sidebar</p>
                    </div>
                    <div class="text-center !p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-purple-600">{{ $stats['campaigns']['hidden_links'] }}</p>
                        <p class="text-sm text-gray-600">Hidden Links</p>
                    </div>
                    <div class="text-center !p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-orange-600">{{ $stats['campaigns']['schedule'] }}</p>
                        <p class="text-sm text-gray-600">Scheduled</p>
                    </div>
                    <div class="text-center !p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-red-600">{{ $stats['campaigns']['sticky'] }}</p>
                        <p class="text-sm text-gray-600">Sticky Posts</p>
                    </div>
                </div>
            </div>

            {{-- Article Statistics --}}
            <div class="content-card">
                <h3 class="text-lg font-semibold !mb-4">Article Statistics</h3>
                <div class="flex items-center gap-6">
                    <div id="articlesPieChart" class="w-40 h-40"></div>
                    <div class="flex-1 grid grid-cols-2 gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <div>
                                <p class="font-semibold text-gray-800">{{ number_format($stats['articles']['unused']) }}</p>
                                <p class="text-sm text-gray-500">Unused</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                            <div>
                                <p class="font-semibold text-gray-800">{{ number_format($stats['articles']['used']) }}</p>
                                <p class="text-sm text-gray-500">Used</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Monthly Performance Chart --}}
            <div class="content-card">
                <h3 class="text-lg font-semibold !mb-4">Monthly Performance (Last 6 Months)</h3>
                <div id="monthlyChart" class="h-64"></div>
            </div>

            {{-- Recent Activity --}}
            <div class="content-card">
                <h3 class="text-lg font-semibold !mb-4">Recent Activity</h3>
                @if(count($recentActivity) > 0)
                    <div class="flex flex-col gap-4">
                        @foreach($recentActivity as $activity)
                            <div class="flex items-start gap-3 !pb-4 border-b border-gray-100 last:border-0">
                                @php
                                    $colorClasses = [
                                        'purple' => 'bg-purple-100 text-purple-600',
                                        'blue' => 'bg-blue-100 text-blue-600',
                                        'green' => 'bg-green-100 text-green-600',
                                        'orange' => 'bg-orange-100 text-orange-600',
                                    ];
                                    $colorClass = $colorClasses[$activity['color']] ?? 'bg-gray-100 text-gray-600';
                                @endphp
                                <div class="w-8 h-8 rounded-lg {{ $colorClass }} flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-sm">{{ $activity['icon'] }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-800 truncate">{{ $activity['title'] }}</p>
                                    <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($activity['date'])->diffForHumans() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">No recent activity</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Super Admin: Other Users Section --}}
    @if($viewingAdmin->isSuperAdmin() && !$isOwnProfile)
        <div class="content-card mt-6">
            <div class="flex items-center justify-between !mb-4">
                <h3 class="text-lg font-semibold">Admin Actions</h3>
            </div>
            <div class="flex gap-4">
                <a href="{{ route('admin.user.edit', $profileAdmin->id) }}" 
                    class="bg-yellow-500 text-white !px-4 !py-2 rounded hover:bg-yellow-600 transition flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">edit</span>
                    Edit User
                </a>
                <a href="{{ route('admin.user.index') }}" 
                    class="bg-gray-500 text-white !px-4 !py-2 rounded hover:bg-gray-600 transition flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    Back to Users
                </a>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
function toggleEditForm() {
    const form = document.getElementById('editProfileForm');
    form.classList.toggle('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    // Monthly Performance Chart
    const monthlyData = @json($monthlyData);
    
    const monthlyOptions = {
        chart: {
            type: 'area',
            height: 256,
            toolbar: { show: false },
            fontFamily: 'Outfit, sans-serif',
        },
        series: [
            { name: 'Articles', data: monthlyData.articles },
            { name: 'Campaigns', data: monthlyData.campaigns }
        ],
        colors: ['#8b5cf6', '#f97316'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.1,
            }
        },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: {
            categories: monthlyData.labels,
            labels: { style: { fontFamily: 'Outfit, sans-serif' } }
        },
        yaxis: {
            labels: { style: { fontFamily: 'Outfit, sans-serif' } }
        },
        grid: { borderColor: '#e5e7eb' },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontFamily: 'Outfit, sans-serif'
        },
        tooltip: {
            style: { fontFamily: 'Outfit, sans-serif' }
        }
    };
    
    new ApexCharts(document.querySelector('#monthlyChart'), monthlyOptions).render();

    // Articles Pie Chart
    const stats = @json($stats);
    
    const pieOptions = {
        chart: {
            type: 'donut',
            height: 160,
            fontFamily: 'Outfit, sans-serif',
        },
        series: [stats.articles.unused, stats.articles.used],
        labels: ['Unused', 'Used'],
        colors: ['#22c55e', '#f97316'],
        plotOptions: {
            pie: {
                donut: {
                    size: '60%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: () => stats.articles.total
                        }
                    }
                }
            }
        },
        legend: { show: false },
        dataLabels: { enabled: false }
    };
    
    new ApexCharts(document.querySelector('#articlesPieChart'), pieOptions).render();
});
</script>
@endpush
