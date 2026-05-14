<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Admin;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleLanguage;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignTask;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Models\Admin\ScheduleSidebarCampaign;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with dynamic data based on user role
     */
    public function index()
    {
        $admin = Auth::guard('admin')->user();

        // Cache dashboard data for 5 minutes to improve performance
        $cacheKey = 'dashboard_data_' . $admin->id . '_' . ($admin->isSuperAdmin() ? 'super' : 'user');

        $dashboardData = cache()->remember($cacheKey, 300, function () use ($admin) {
            return [
                'stats' => $this->getStats($admin),
                'chartData' => $this->getChartData($admin),
                'campaigns' => $this->getRecentCampaigns($admin),
                'domainCategories' => $this->getDomainCategories($admin),
                'articleUsage' => $this->getArticleUsage($admin),
                'articlesByLanguage' => $this->getArticlesByLanguage($admin),
            ];
        });

        return view('admin.welcome', array_merge(
            ['admin' => $admin],
            $dashboardData
        ));
    }

    /**
     * Get statistics based on user role
     */
    private function getStats(Admin $admin): array
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        // Batch all count queries in a single optimized query set
        if ($isSuperAdmin) {
            // Super admin: get all counts in parallel
            $usersCount = Admin::count();
            $membersCount = Admin::where('type', Admin::MEMBER)->count();
            $domainsCount = Domain::count();
        } else {
            // Regular user: scoped counts
            $usersCount = 1;
            $membersCount = 0;
            $domainsCount = Domain::where('admin_id', $admin->id)->count();
        }

        // Optimize article count query with proper indexing
        $articlesCount = Article::query()
            ->whereNull('lock_at')
            ->whereNull('deleted_at')
            ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
            ->count();

        // Batch campaign counts using a single query with conditional aggregation
        $campaignCounts = DB::table('campaigns')
            ->selectRaw('COUNT(*) as total')
            ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
            ->first();

        $postCampaignsCount = $campaignCounts->total ?? 0;

        // Schedule campaigns counts
        $scheduleCounts = DB::table('schedule_campaigns')
            ->selectRaw('
                SUM(CASE WHEN is_sticky_campaign = 0 THEN 1 ELSE 0 END) as regular,
                SUM(CASE WHEN is_sticky_campaign = 1 THEN 1 ELSE 0 END) as sticky
            ')
            ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
            ->first();

        $schedulePostCampaignsCount = $scheduleCounts->regular ?? 0;
        $scheduleStickyPostCampaignsCount = $scheduleCounts->sticky ?? 0;

        // Schedule Sidebar Campaigns count
        $scheduleSidebarCampaignsCount = ScheduleSidebarCampaign::query()
            ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
            ->count();

        return [
            [
                'count' => $usersCount,
                'label' => 'No of Users',
                'bg' => 'bg-green-200',
                'stroke' => 'green',
                'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                'visible' => true,
            ],
            [
                'count' => $membersCount,
                'label' => 'No of Members',
                'bg' => 'bg-gray-700',
                'stroke' => '#e5e7eb',
                'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                'visible' => $isSuperAdmin,
            ],
            [
                'count' => $domainsCount,
                'label' => 'No of Domains',
                'bg' => 'bg-orange-100',
                'stroke' => 'orange',
                'icon' => '<circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>',
                'visible' => true,
            ],
            [
                'count' => $articlesCount,
                'label' => 'No of Articles',
                'bg' => 'bg-purple-100',
                'stroke' => 'purple',
                'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>',
                'visible' => true,
            ],
            [
                'count' => $postCampaignsCount,
                'label' => 'Post Campaigns',
                'bg' => 'bg-red-200',
                'stroke' => 'red',
                'icon' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>',
                'visible' => true,
            ],
            [
                'count' => $schedulePostCampaignsCount,
                'label' => 'Schedule Post Campaigns',
                'bg' => 'bg-[#8cdcff]',
                'stroke' => '#0094d4',
                'icon' => '<circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>',
                'visible' => true,
            ],
            [
                'count' => $scheduleStickyPostCampaignsCount,
                'label' => 'Schedule Sticky Post Campaigns',
                'bg' => 'bg-[#b8e0ff]',
                'stroke' => '#0077b6',
                'icon' => '<circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>',
                'visible' => true,
            ],
            [
                'count' => $scheduleSidebarCampaignsCount,
                'label' => 'Schedule Sidebar Campaigns',
                'bg' => 'bg-[#ffc7bd]',
                'stroke' => '#ff6e54',
                'icon' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="9" y1="3" x2="9" y2="21"></line>',
                'visible' => true,
            ],
        ];
    }

    /**
     * Get articles grouped by language
     */
    private function getArticlesByLanguage(Admin $admin): array
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        // Optimize with a direct join query instead of withCount
        $languages = DB::table('article_languages')
            ->leftJoin('articles', 'article_languages.id', '=', 'articles.article_language_id')
            ->select('article_languages.id', 'article_languages.name', DB::raw('COUNT(articles.id) as article_count'))
            ->whereNull('articles.lock_at')
            ->whereNull('articles.deleted_at')
            ->when(!$isSuperAdmin, fn($q) => $q->where('articles.admin_id', $admin->id))
            ->groupBy('article_languages.id', 'article_languages.name')
            ->having('article_count', '>', 0)
            ->orderBy('article_count', 'desc')
            ->get();

        return [
            'labels' => $languages->pluck('name')->toArray(),
            'data' => $languages->pluck('article_count')->toArray(),
            'colors' => $this->generateColors(count($languages)),
        ];
    }

    /**
     * Generate colors for chart
     */
    private function generateColors(int $count): array
    {
        $baseColors = [
            '#ff4a17',
            '#3b82f6',
            '#22c55e',
            '#f59e0b',
            '#8b5cf6',
            '#ec4899',
            '#14b8a6',
            '#f97316',
            '#6366f1',
            '#84cc16',
            '#06b6d4',
            '#ef4444',
            '#a855f7',
            '#10b981',
            '#f43f5e',
        ];

        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $baseColors[$i % count($baseColors)];
        }

        return $colors;
    }

    /**
     * Get chart data for PBN campaigns
     */
    private function getChartData(Admin $admin): array
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        // Get last 12 months
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i));
        }

        $categories = $months->map(fn($m) => $m->format('M/y'))->toArray();

        // PBN Post Campaigns (regular campaigns)
        $pbnPostData = $this->getMonthlyData(Campaign::class, $admin, $months, $isSuperAdmin);

        // Sidebar Campaigns (blogroll)
        $sidebarData = $this->getMonthlyData(SidebarCampaign::class, $admin, $months, $isSuperAdmin);

        // Hidden Links Campaigns (sticky equivalent)
        $hiddenLinkData = $this->getMonthlyData(HiddenLinksCampaign::class, $admin, $months, $isSuperAdmin);

        return [
            'categories' => $categories,
            'pbnPostCampaigns' => $pbnPostData,
            'pbnBlogrollCampaigns' => $sidebarData,
            'pbnStickyCampaigns' => $hiddenLinkData,
        ];
    }

    /**
     * Get monthly campaign data
     */
    private function getMonthlyData(string $model, Admin $admin, $months, bool $isSuperAdmin): array
    {
        $start = $months->first()->copy()->startOfMonth();
        $end = $months->last()->copy()->endOfMonth();

        $monthlyCounts = $model::query()
            ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('YEAR(created_at) as y, MONTH(created_at) as m, COUNT(*) as total')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn($row) => $row->y . '-' . $row->m);

        return $months
            ->map(function ($month) use ($monthlyCounts) {
                $key = $month->year . '-' . $month->month;
                return (int) optional($monthlyCounts->get($key))->total;
            })
            ->toArray();
    }

    /**
     * Get recent campaigns based on type
     */
    public function getRecentCampaigns(Admin $admin, string $type = 'post'): array
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        $campaigns = collect();

        switch ($type) {
            case 'sidebar':
                $campaigns = SidebarCampaign::query()
                    ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
                    ->with(['domainCategory:id,name'])
                    ->withCount(['links', 'domains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn($c) => [
                        'campaign' => $c->campaign_no,
                        'domain' => $c->domainCategory->name ?? 'N/A',
                        'quantity' => $c->total_targets ?? 0,
                        'links' => $c->links_count ?? 0,
                        'keywords' => $c->sidebar_count ?? 0,
                        'date' => $c->created_at->format('d-m-Y'),
                        'id' => $c->id,
                        'type' => 'sidebar',
                    ]);
                break;

            case 'dripfeed':
            case 'schedule':
                $campaigns = ScheduleCampaign::query()
                    ->where('is_sticky_campaign', false)
                    ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
                    ->with(['domainCategory:id,name'])
                    ->withCount(['articles', 'domains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn($c) => [
                        'campaign' => $c->campaign_no,
                        'domain' => $c->domainCategory->name ?? 'N/A',
                        'quantity' => $c->total_targets ?? 0,
                        'links' => $c->articles_count ?? 0,
                        'keywords' => $c->domains_count ?? 0,
                        'date' => $c->created_at->format('d-m-Y'),
                        'id' => $c->id,
                        'type' => 'schedule',
                    ]);
                break;

            case 'schedule_sticky':
                $campaigns = ScheduleCampaign::query()
                    ->where('is_sticky_campaign', true)
                    ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
                    ->with(['domainCategory:id,name'])
                    ->withCount(['articles', 'domains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn($c) => [
                        'campaign' => $c->campaign_no,
                        'domain' => $c->domainCategory->name ?? 'N/A',
                        'quantity' => $c->total_targets ?? 0,
                        'links' => $c->articles_count ?? 0,
                        'keywords' => $c->domains_count ?? 0,
                        'date' => $c->created_at->format('d-m-Y'),
                        'id' => $c->id,
                        'type' => 'schedule_sticky',
                    ]);
                break;

            case 'sticky':
                $campaigns = Campaign::query()
                    ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
                    ->where('is_sticky_campaign', true)
                    ->with(['domainCategory:id,name'])
                    ->withCount(['campaignArticles', 'campaignDomains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn($c) => [
                        'campaign' => $c->campaign_no,
                        'domain' => $c->domainCategory->name ?? 'N/A',
                        'quantity' => $c->total_targets ?? 0,
                        'links' => $c->campaign_articles_count ?? 0,
                        'keywords' => $c->campaign_domains_count ?? 0,
                        'date' => $c->created_at->format('d-m-Y'),
                        'id' => $c->id,
                        'type' => 'sticky',
                    ]);
                break;

            default: // post
                $campaigns = Campaign::query()
                    ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
                    ->where(fn($q) => $q->whereNull('is_sticky_campaign')->orWhere('is_sticky_campaign', false))
                    ->with(['domainCategory:id,name'])
                    ->withCount(['campaignArticles', 'campaignDomains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn($c) => [
                        'campaign' => $c->campaign_no,
                        'domain' => $c->domainCategory->name ?? 'N/A',
                        'quantity' => $c->total_targets ?? 0,
                        'links' => $c->campaign_articles_count ?? 0,
                        'keywords' => $c->campaign_domains_count ?? 0,
                        'date' => $c->created_at->format('d-m-Y'),
                        'id' => $c->id,
                        'type' => 'post',
                    ]);
                break;
        }

        return $campaigns->toArray();
    }

    /**
     * Get domain categories with domain counts
     */
    private function getDomainCategories(Admin $admin): array
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        // Optimize with direct join query
        $categories = DB::table('domain_categories')
            ->leftJoin('domains', 'domain_categories.id', '=', 'domains.domain_category_id')
            ->select('domain_categories.id', 'domain_categories.name', DB::raw('COUNT(domains.id) as domains_count'))
            ->when(!$isSuperAdmin, fn($q) => $q->where('domain_categories.admin_id', $admin->id))
            ->groupBy('domain_categories.id', 'domain_categories.name')
            ->orderBy('domains_count', 'desc')
            ->limit(10)
            ->get();

        return $categories->map(fn($dc) => [
            'domain' => $dc->name,
            'quantity' => $dc->domains_count,
            'id' => $dc->id,
        ])->toArray();
    }

    /**
     * Get article usage statistics
     */
    private function getArticleUsage(Admin $admin): array
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        // Single optimized query with conditional aggregation
        $stats = DB::table('articles')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? OR lock_at IS NOT NULL THEN 1 ELSE 0 END) as used
            ', [Article::STATUS_USED])
            ->when(!$isSuperAdmin, fn($q) => $q->where('admin_id', $admin->id))
            ->whereNull('deleted_at')
            ->first();

        $totalArticles = $stats->total ?? 0;
        $usedArticles = $stats->used ?? 0;
        $remaining = $totalArticles - $usedArticles;
        $percentage = $totalArticles > 0 ? round(($usedArticles / $totalArticles) * 100) : 0;

        return [
            'total' => $totalArticles,
            'used' => $usedArticles,
            'remaining' => $remaining,
            'percentage' => $percentage,
        ];
    }

    /**
     * API endpoint to get campaigns by type (for AJAX)
     */
    public function getCampaignsByType(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        $type = $request->get('type', 'post');

        $campaigns = $this->getRecentCampaigns($admin, $type);

        return response()->json([
            'success' => true,
            'campaigns' => $campaigns,
        ]);
    }
}
