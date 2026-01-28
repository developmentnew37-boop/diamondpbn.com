<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\Admin;
use App\Models\Admin\Article;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\Campaign;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Display the current user's profile
     */
    public function index()
    {
        $admin = Auth::guard('admin')->user();

        return $this->showProfile($admin);
    }

    /**
     * Display a specific user's profile (Super Admin only for other users)
     */
    public function show(string $slug)
    {
        $currentAdmin = Auth::guard('admin')->user();
        $profileAdmin = Admin::where('slug', $slug)->firstOrFail();

        // Only Super Admin can view other users' profiles
        if (!$currentAdmin->isSuperAdmin() && $currentAdmin->id !== $profileAdmin->id) {
            return redirect()->route('admin.profile')
                ->with('cus__error', 'You do not have permission to view this profile');
        }

        return $this->showProfile($profileAdmin, $currentAdmin);
    }

    /**
     * Show profile with statistics
     */
    private function showProfile(Admin $profileAdmin, ?Admin $viewingAdmin = null)
    {
        $viewingAdmin = $viewingAdmin ?? $profileAdmin;
        $isOwnProfile = $viewingAdmin->id === $profileAdmin->id;
        $canEdit = $isOwnProfile || $viewingAdmin->isSuperAdmin();

        // Get user statistics
        $stats = $this->getUserStats($profileAdmin);

        // Get recent activity
        $recentActivity = $this->getRecentActivity($profileAdmin);

        // Get monthly performance data for charts
        $monthlyData = $this->getMonthlyData($profileAdmin);

        return view('admin.profile.index', compact(
            'profileAdmin',
            'viewingAdmin',
            'isOwnProfile',
            'canEdit',
            'stats',
            'recentActivity',
            'monthlyData'
        ));
    }

    /**
     * Get user statistics
     */
    private function getUserStats(Admin $admin): array
    {
        return [
            'articles' => [
                'total' => Article::where('admin_id', $admin->id)->count(),
                'used' => Article::where('admin_id', $admin->id)
                    ->where(function ($query) {
                        $query->where('status', Article::STATUS_USED)
                            ->orWhereNotNull('lock_at');
                    })
                    ->count(),


                'unused' => Article::where('admin_id', $admin->id)
                    ->where(function ($query) {
                        $query->where('status', Article::STATUS_UNUSED)
                            ->whereNull('lock_at');
                    })
                    ->count(),
            ],
            'domains' => [
                'total' => Domain::where('admin_id', $admin->id)->count(),
            ],
            'domain_categories' => [
                'total' => DomainCategory::where('admin_id', $admin->id)->count(),
            ],
            'campaigns' => [
                'pbn_posts' => Campaign::where('admin_id', $admin->id)
                    ->where(fn($q) => $q->whereNull('is_sticky_campaign')->orWhere('is_sticky_campaign', false))
                    ->count(),
                'sidebar' => SidebarCampaign::where('admin_id', $admin->id)->count(),
                'hidden_links' => HiddenLinksCampaign::where('admin_id', $admin->id)->count(),
                'schedule' => ScheduleCampaign::where('admin_id', $admin->id)->count(),
                'sticky' => Campaign::where('admin_id', $admin->id)->where('is_sticky_campaign', true)->count(),
            ],
            'total_campaigns' => Campaign::where('admin_id', $admin->id)->count()
                + SidebarCampaign::where('admin_id', $admin->id)->count()
                + HiddenLinksCampaign::where('admin_id', $admin->id)->count()
                + ScheduleCampaign::where('admin_id', $admin->id)->count(),
        ];
    }

    /**
     * Get recent activity for user
     */
    private function getRecentActivity(Admin $admin): array
    {
        $activities = collect();

        // Recent Articles
        $recentArticles = Article::where('admin_id', $admin->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($a) => [
                'type' => 'article',
                'title' => 'Created article: ' . Str::limit($a->name, 30),
                'date' => $a->created_at,
                'icon' => 'article',
                'color' => 'purple',
            ]);
        $activities = $activities->merge($recentArticles);

        // Recent Campaigns
        $recentCampaigns = Campaign::where('admin_id', $admin->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($c) => [
                'type' => 'campaign',
                'title' => 'Created campaign: ' . $c->campaign_no,
                'date' => $c->created_at,
                'icon' => 'campaign',
                'color' => 'blue',
            ]);
        $activities = $activities->merge($recentCampaigns);

        // Recent Sidebar Campaigns
        $recentSidebar = SidebarCampaign::where('admin_id', $admin->id)
            ->latest()
            ->take(3)
            ->get()
            ->map(fn($c) => [
                'type' => 'sidebar_campaign',
                'title' => 'Created sidebar campaign: ' . $c->campaign_no,
                'date' => $c->created_at,
                'icon' => 'view_sidebar',
                'color' => 'green',
            ]);
        $activities = $activities->merge($recentSidebar);

        return $activities->sortByDesc('date')->take(10)->values()->toArray();
    }

    /**
     * Get monthly data for charts
     */
    private function getMonthlyData(Admin $admin): array
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i));
        }

        $labels = $months->map(fn($m) => $m->format('M Y'))->toArray();

        $articlesData = $months->map(function ($month) use ($admin) {
            return Article::where('admin_id', $admin->id)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
        })->toArray();

        $campaignsData = $months->map(function ($month) use ($admin) {
            return Campaign::where('admin_id', $admin->id)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
        })->toArray();

        return [
            'labels' => $labels,
            'articles' => $articlesData,
            'campaigns' => $campaignsData,
        ];
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('admins')->ignore($admin->id)],
            'current_password' => ['nullable', 'required_with:new_password'],
            'new_password' => ['nullable', 'min:8', 'confirmed'],
        ]);

        // Verify current password if changing password
        if ($request->filled('current_password')) {
            if (!Hash::check($request->current_password, $admin->password)) {
                return back()->with('cus__error', 'Current password is incorrect');
            }
            $admin->password = $validated['new_password'];
        }

        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        $admin->save();

        return back()->with('cus__success', 'Profile updated successfully');
    }
}
