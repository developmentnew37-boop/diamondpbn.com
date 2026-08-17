<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Roles;
use App\Models\Admin\Article;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\Campaign;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\WpScheduledCampaign;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $admin = Auth::guard('admin')->user();

        if ($admin->isSuperAdmin()) {
            // SUPER ADMIN → see all users
            $users = Admin::paginate(10);
            $paginate = true;
        } else {
            // NORMAL ADMIN/MEMBER → see only themselves
            $users = Admin::where('id', $admin->id)->get();
            $paginate = false;
        }

        return view('admin.users.users', compact('users', 'paginate'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $admin = Auth::guard('admin')->user();
        
        // Only Super Admin can create users
        if (!$admin->isSuperAdmin()) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You do not have permission to create users');
        }
        
        $roles = Roles::all();
        $featurePermissions = config('admin_permissions', []);

        return view('admin.users.create-user', compact('roles', 'featurePermissions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        
        // Only Super Admin can create users
        if (!$admin->isSuperAdmin()) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You do not have permission to create users');
        }
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:admins,name'],
            'email' => ['required', 'email', 'unique:admins,email'],
            'roles' => [
                'required',
                'integer',
                Rule::exists('roles', 'role_id')->where(function ($query) {
                    $query->whereIn('role_id', [Admin::ADMIN, Admin::MEMBER]);
                }),
            ],
            'password' => ['required', 'min:8', 'confirmed'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(config('admin_permissions', [])))],
        ]);

        $user = Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'type' => $validated['roles'],
            'password' => $validated['password'],
        ]);

        $user->syncFeaturePermissions($validated['permissions'] ?? []);

        return redirect()->route('admin.user.index')
            ->with('cus__success', 'User created successfully: ' . $validated['email']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $admin = Auth::guard('admin')->user();
        $user = Admin::findOrFail($id);
        $range = request()->query('range', 'year');
        $range = in_array($range, ['day', 'week', 'year'], true) ? $range : 'year';
        
        // Only Super Admin can view other users, or user can view themselves
        if (!$admin->isSuperAdmin() && $admin->id !== $user->id) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You do not have permission to view this user');
        }
        
        // Get user statistics
        $stats = [
            'articles' => Article::where('admin_id', $user->id)->count(),
            'domains' => Domain::where('admin_id', $user->id)->count(),
            'domain_categories' => DomainCategory::where('admin_id', $user->id)->count(),
            'campaigns' => Campaign::where('admin_id', $user->id)->count(),
            'sidebar_campaigns' => SidebarCampaign::where('admin_id', $user->id)->count(),
            'hidden_link_campaigns' => HiddenLinksCampaign::where('admin_id', $user->id)->count(),
            'schedule_campaigns' => ScheduleCampaign::where('admin_id', $user->id)->count(),
        ];

        $creationSummary = $this->getUserCreationSummary($user, $range);
        
        $roles = Roles::all();
        $featurePermissions = config('admin_permissions', []);
        $assignedPermissions = $user->isSuperAdmin()
            ? array_keys($featurePermissions)
            : $user->permissionKeys();

        return view('admin.users.show-user', compact(
            'user',
            'stats',
            'roles',
            'creationSummary',
            'range',
            'featurePermissions',
            'assignedPermissions'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $admin = Auth::guard('admin')->user();
        $user = Admin::findOrFail($id);
        
        // Only Super Admin can edit other users, or user can edit themselves
        if (!$admin->isSuperAdmin() && $admin->id !== $user->id) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You do not have permission to edit this user');
        }
        
        // Cannot edit a Super Admin unless you are that Super Admin
        if ($user->isSuperAdmin() && $admin->id !== $user->id) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You cannot edit another Super Admin');
        }
        
        $roles = Roles::all();
        $featurePermissions = config('admin_permissions', []);
        $assignedPermissions = $user->permissionKeys();

        return view('admin.users.edit-user', compact('user', 'roles', 'featurePermissions', 'assignedPermissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $admin = Auth::guard('admin')->user();
        $user = Admin::findOrFail($id);
        
        // Only Super Admin can update other users
        if (!$admin->isSuperAdmin() && $admin->id !== $user->id) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You do not have permission to update this user');
        }
        
        // Cannot change another Super Admin
        if ($user->isSuperAdmin() && $admin->id !== $user->id) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You cannot update another Super Admin');
        }
        
        $rules = [
            'name' => ['required', 'string', 'max:100', Rule::unique('admins')->ignore($user->id)],
            'email' => ['required', 'email', Rule::unique('admins')->ignore($user->id)],
        ];
        
        // Only Super Admin can change roles / feature permissions on other users.
        // Cannot promote to Super Admin via this form (same as create).
        if ($admin->isSuperAdmin() && $admin->id !== $user->id) {
            $rules['roles'] = [
                'required',
                'integer',
                Rule::exists('roles', 'role_id')->where(function ($query) {
                    $query->whereIn('role_id', [Admin::ADMIN, Admin::MEMBER]);
                }),
            ];
            $rules['permissions'] = ['nullable', 'array'];
            $rules['permissions.*'] = ['string', Rule::in(array_keys(config('admin_permissions', [])))];
        }
        
        // Password is optional on update
        if ($request->filled('password')) {
            $rules['password'] = ['min:8', 'confirmed'];
        }
        
        $validated = $request->validate($rules);
        
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        
        if ($admin->isSuperAdmin() && $admin->id !== $user->id && isset($validated['roles'])) {
            $user->type = $validated['roles'];
        }
        
        if ($request->filled('password')) {
            $user->password = $validated['password'];
        }
        
        $user->save();

        if ($admin->isSuperAdmin() && ! $user->isSuperAdmin()) {
            // syncFeaturePermissions clears grants when role is not Admin.
            $user->syncFeaturePermissions($validated['permissions'] ?? []);
        }

        return redirect()->route('admin.user.index')
            ->with('cus__success', 'User updated successfully: ' . $user->email);
    }

    /**
     * Remove the specified resource from storage.
     * Transfer all data to Super Admin before deleting
     */
    public function destroy(string $id)
    {
        $admin = Auth::guard('admin')->user();
        $user = Admin::findOrFail($id);
        
        // Only Super Admin can delete users
        if (!$admin->isSuperAdmin()) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You do not have permission to delete users');
        }
        
        // Cannot delete yourself
        if ($admin->id === $user->id) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You cannot delete yourself');
        }
        
        // Cannot delete another Super Admin
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'You cannot delete another Super Admin');
        }
        
        // Get the first Super Admin (preferably the current one)
        $superAdmin = $admin;
        
        DB::beginTransaction();
        
        try {
            // Transfer all user data to Super Admin
            $transferCount = $this->transferUserData($user->id, $superAdmin->id);
            
            // Delete the user
            $userName = $user->name;
            $user->delete();
            
            DB::commit();
            
            return redirect()->route('admin.user.index')
                ->with('cus__success', "User '{$userName}' deleted successfully. {$transferCount} items transferred to your account.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->route('admin.user.index')
                ->with('cus__error', 'Error deleting user: ' . $e->getMessage());
        }
    }
    
    /**
     * Transfer all data from one user to another
     */
    private function transferUserData(int $fromUserId, int $toUserId): int
    {
        $count = 0;
        
        // Transfer Articles
        $count += Article::where('admin_id', $fromUserId)->update(['admin_id' => $toUserId]);
        
        // Transfer Domains
        $count += Domain::where('admin_id', $fromUserId)->update(['admin_id' => $toUserId]);
        
        // Transfer Domain Categories
        $count += DomainCategory::where('admin_id', $fromUserId)->update(['admin_id' => $toUserId]);
        
        // Transfer Article Categories
        $count += ArticleCategory::where('admin_id', $fromUserId)->update(['admin_id' => $toUserId]);
        
        // Transfer Campaigns
        $count += Campaign::where('admin_id', $fromUserId)->update(['admin_id' => $toUserId]);
        
        // Transfer Sidebar Campaigns
        $count += SidebarCampaign::where('admin_id', $fromUserId)->update(['admin_id' => $toUserId]);
        
        // Transfer Hidden Links Campaigns
        $count += HiddenLinksCampaign::where('admin_id', $fromUserId)->update(['admin_id' => $toUserId]);
        
        // Transfer Schedule Campaigns
        $count += ScheduleCampaign::where('admin_id', $fromUserId)->update(['admin_id' => $toUserId]);
        
        // Transfer Schedule Sidebar Campaigns
        $count += ScheduleSidebarCampaign::where('admin_id', $fromUserId)->update(['admin_id' => $toUserId]);
        
        return $count;
    }

    /**
     * Summary counts for selected period (1 day / 1 week / 1 year)
     */
    private function getUserCreationSummary(Admin $user, string $range): array
    {
        $startAt = match ($range) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            default => now()->subYear(),
        };

        $articles = Article::where('admin_id', $user->id)
            ->where('created_at', '>=', $startAt)
            ->count();

        // "Campaigns" here means normal PBN campaigns (non-sticky)
        $campaigns = Campaign::where('admin_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('is_sticky_campaign')
                    ->orWhere('is_sticky_campaign', false);
            })
            ->where('created_at', '>=', $startAt)
            ->count();

        $sidebarCampaigns = SidebarCampaign::where('admin_id', $user->id)
            ->where('created_at', '>=', $startAt)
            ->count();

        $hiddenLinksCampaigns = HiddenLinksCampaign::where('admin_id', $user->id)
            ->where('created_at', '>=', $startAt)
            ->count();

        $scheduleCampaigns = ScheduleCampaign::where('admin_id', $user->id)
            ->where('created_at', '>=', $startAt)
            ->count();

        $scheduleSidebarCampaigns = ScheduleSidebarCampaign::where('admin_id', $user->id)
            ->where('created_at', '>=', $startAt)
            ->count();

        $wpScheduledCampaigns = WpScheduledCampaign::where('admin_id', $user->id)
            ->where('created_at', '>=', $startAt)
            ->count();

        $allScheduleCampaigns = $scheduleCampaigns + $scheduleSidebarCampaigns + $wpScheduledCampaigns;

        return [
            'range_label' => match ($range) {
                'day' => 'Last 1 Day',
                'week' => 'Last 1 Week',
                default => 'Last 1 Year',
            },
            'start_at' => Carbon::parse($startAt),
            'rows' => [
                ['label' => 'Articles', 'count' => $articles],
                ['label' => 'Campaigns', 'count' => $campaigns],
                ['label' => 'Sidebar Campaigns', 'count' => $sidebarCampaigns],
                ['label' => 'Hidden Links Campaigns', 'count' => $hiddenLinksCampaigns],
                ['label' => 'Schedule Campaigns (Post)', 'count' => $scheduleCampaigns],
                ['label' => 'Schedule Sidebar Campaigns', 'count' => $scheduleSidebarCampaigns],
                ['label' => 'WP Scheduled Campaigns', 'count' => $wpScheduledCampaigns],
                ['label' => 'All Schedule Campaigns', 'count' => $allScheduleCampaigns],
            ],
        ];
    }
}
