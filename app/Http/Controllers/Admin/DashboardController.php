<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Admin\Article;
use App\Models\Admin\Campaign;
use App\Models\Admin\Domain;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the dashboard without blocking first paint on heavy aggregations.
     */
    public function index()
    {
        $admin = Auth::guard('admin')->user();
        $cacheKey = $this->dashboardCacheKey($admin);

        $fresh = cache()->get($cacheKey);
        $dashboardData = $fresh
            ?? cache()->get($cacheKey.'_stale')
            ?? $this->emptyDashboardData($admin);

        return view('admin.welcome', array_merge(
            [
                'admin' => $admin,
                'loadAnalytics' => $fresh === null,
            ],
            $dashboardData
        ));
    }

    /**
     * JSON payload for charts, counts, and tables (cached).
     */
    public function analytics()
    {
        $admin = Auth::guard('admin')->user();

        return response()->json([
            'success' => true,
            'data' => $this->rememberDashboardData($admin),
        ]);
    }

    /**
     * Get statistics based on user role
     */
    private function getStats(Admin $admin, object $articleAgg): array
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        if ($isSuperAdmin) {
            $usersCount = Admin::count();
            $membersCount = Admin::where('type', Admin::MEMBER)->count();
            $domainsCount = Domain::count();
        } else {
            $usersCount = 1;
            $membersCount = 0;
            $domainsCount = Domain::where('admin_id', $admin->id)->count();
        }

        $articlesCount = (int) ($articleAgg->unlocked ?? 0);

        $campaignCounts = DB::table('campaigns')
            ->selectRaw('COUNT(*) as total')
            ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
            ->first();

        $postCampaignsCount = $campaignCounts->total ?? 0;

        $scheduleCounts = DB::table('schedule_campaigns')
            ->selectRaw('
                SUM(CASE WHEN is_sticky_campaign = 0 THEN 1 ELSE 0 END) as regular,
                SUM(CASE WHEN is_sticky_campaign = 1 THEN 1 ELSE 0 END) as sticky
            ')
            ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
            ->first();

        $schedulePostCampaignsCount = $scheduleCounts->regular ?? 0;
        $scheduleStickyPostCampaignsCount = $scheduleCounts->sticky ?? 0;

        $scheduleSidebarCampaignsCount = ScheduleSidebarCampaign::query()
            ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
            ->count();

        return $this->statsCards($isSuperAdmin, [
            'users' => $usersCount,
            'members' => $membersCount,
            'domains' => $domainsCount,
            'articles' => $articlesCount,
            'posts' => $postCampaignsCount,
            'schedule_posts' => $schedulePostCampaignsCount,
            'schedule_sticky' => $scheduleStickyPostCampaignsCount,
            'schedule_sidebar' => $scheduleSidebarCampaignsCount,
        ]);
    }

    /**
     * Get articles grouped by language
     */
    private function getArticlesByLanguage(Admin $admin): array
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        $counts = DB::table('articles')
            ->select('article_language_id', DB::raw('COUNT(*) as article_count'))
            ->whereNull('deleted_at')
            ->whereNull('lock_at')
            ->whereNotNull('article_language_id')
            ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
            ->groupBy('article_language_id')
            ->orderByDesc('article_count')
            ->get();

        $names = DB::table('article_languages')
            ->whereIn('id', $counts->pluck('article_language_id')->filter()->all())
            ->pluck('name', 'id');

        $languages = $counts
            ->map(fn ($row) => (object) [
                'name' => $names[$row->article_language_id] ?? 'Unknown',
                'article_count' => (int) $row->article_count,
            ])
            ->filter(fn ($row) => $row->article_count > 0)
            ->values();

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

        $months = $this->lastTwelveMonths();
        $categories = $months->map(fn ($m) => $m->format('M/y'))->toArray();

        return [
            'categories' => $categories,
            'pbnPostCampaigns' => $this->getMonthlyData(Campaign::class, $admin, $months, $isSuperAdmin),
            'pbnBlogrollCampaigns' => $this->getMonthlyData(SidebarCampaign::class, $admin, $months, $isSuperAdmin),
            'pbnStickyCampaigns' => $this->getMonthlyData(HiddenLinksCampaign::class, $admin, $months, $isSuperAdmin),
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
            ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('YEAR(created_at) as y, MONTH(created_at) as m, COUNT(*) as total')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($row) => $row->y.'-'.$row->m);

        return $months
            ->map(function ($month) use ($monthlyCounts) {
                $key = $month->year.'-'.$month->month;

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
                    ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
                    ->with(['domainCategory:id,name'])
                    ->withCount(['links', 'domains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn ($c) => [
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
                    ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
                    ->with(['domainCategory:id,name'])
                    ->withCount(['articles', 'domains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn ($c) => [
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
                    ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
                    ->with(['domainCategory:id,name'])
                    ->withCount(['articles', 'domains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn ($c) => [
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
                    ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
                    ->where('is_sticky_campaign', true)
                    ->with(['domainCategory:id,name'])
                    ->withCount(['campaignArticles', 'campaignDomains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn ($c) => [
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
                    ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
                    ->where(fn ($q) => $q->whereNull('is_sticky_campaign')->orWhere('is_sticky_campaign', false))
                    ->with(['domainCategory:id,name'])
                    ->withCount(['campaignArticles', 'campaignDomains'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn ($c) => [
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

        $categories = DB::table('domain_categories')
            ->leftJoin('domains', 'domain_categories.id', '=', 'domains.domain_category_id')
            ->select('domain_categories.id', 'domain_categories.name', DB::raw('COUNT(domains.id) as domains_count'))
            ->when(! $isSuperAdmin, fn ($q) => $q->where('domain_categories.admin_id', $admin->id))
            ->groupBy('domain_categories.id', 'domain_categories.name')
            ->orderBy('domains_count', 'desc')
            ->limit(10)
            ->get();

        return $categories->map(fn ($dc) => [
            'domain' => $dc->name,
            'quantity' => $dc->domains_count,
            'id' => $dc->id,
        ])->toArray();
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

    private function dashboardCacheKey(Admin $admin): string
    {
        return 'dashboard_data_v2_'.$admin->id.'_'.($admin->isSuperAdmin() ? 'super' : 'user');
    }

    private function rememberDashboardData(Admin $admin): array
    {
        $cacheKey = $this->dashboardCacheKey($admin);

        return cache()->remember($cacheKey, 900, function () use ($admin, $cacheKey) {
            $data = $this->buildDashboardData($admin);
            cache()->put($cacheKey.'_stale', $data, 86400);

            return $data;
        });
    }

    private function buildDashboardData(Admin $admin): array
    {
        $articleAgg = $this->getArticleAggregates($admin);

        return [
            'stats' => $this->getStats($admin, $articleAgg),
            'chartData' => $this->getChartData($admin),
            'campaigns' => $this->getRecentCampaigns($admin),
            'domainCategories' => $this->getDomainCategories($admin),
            'articleUsage' => $this->formatArticleUsage($articleAgg),
            'articlesByLanguage' => $this->getArticlesByLanguage($admin),
            'userRoleCounts' => $this->getUserRoleCounts($admin),
        ];
    }

    private function emptyDashboardData(Admin $admin): array
    {
        return [
            'stats' => $this->statsCards($admin->isSuperAdmin(), [
                'users' => 0,
                'members' => 0,
                'domains' => 0,
                'articles' => 0,
                'posts' => 0,
                'schedule_posts' => 0,
                'schedule_sticky' => 0,
                'schedule_sidebar' => 0,
            ]),
            'chartData' => $this->emptyChartData(),
            'campaigns' => [],
            'domainCategories' => [],
            'articleUsage' => [
                'total' => 0,
                'used' => 0,
                'remaining' => 0,
                'percentage' => 0,
            ],
            'articlesByLanguage' => [
                'labels' => [],
                'data' => [],
                'colors' => [],
            ],
            'userRoleCounts' => [
                'super' => 0,
                'admin' => 0,
                'member' => 0,
            ],
        ];
    }

    private function emptyChartData(): array
    {
        $months = $this->lastTwelveMonths();

        return [
            'categories' => $months->map(fn ($m) => $m->format('M/y'))->toArray(),
            'pbnPostCampaigns' => array_fill(0, 12, 0),
            'pbnBlogrollCampaigns' => array_fill(0, 12, 0),
            'pbnStickyCampaigns' => array_fill(0, 12, 0),
        ];
    }

    private function lastTwelveMonths()
    {
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i));
        }

        return $months;
    }

    private function getArticleAggregates(Admin $admin): object
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        return DB::table('articles')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN lock_at IS NULL THEN 1 ELSE 0 END) as unlocked,
                SUM(CASE WHEN status = ? OR lock_at IS NOT NULL THEN 1 ELSE 0 END) as used
            ', [Article::STATUS_USED])
            ->whereNull('deleted_at')
            ->when(! $isSuperAdmin, fn ($q) => $q->where('admin_id', $admin->id))
            ->first() ?? (object) ['total' => 0, 'unlocked' => 0, 'used' => 0];
    }

    private function formatArticleUsage(object $stats): array
    {
        $totalArticles = (int) ($stats->total ?? 0);
        $usedArticles = (int) ($stats->used ?? 0);
        $remaining = $totalArticles - $usedArticles;
        $percentage = $totalArticles > 0 ? round(($usedArticles / $totalArticles) * 100) : 0;

        return [
            'total' => $totalArticles,
            'used' => $usedArticles,
            'remaining' => $remaining,
            'percentage' => $percentage,
        ];
    }

    private function getUserRoleCounts(Admin $admin): array
    {
        if (! $admin->isSuperAdmin()) {
            return ['super' => 0, 'admin' => 0, 'member' => 0];
        }

        $counts = Admin::query()
            ->selectRaw('
                SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as super_count,
                SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as admin_count,
                SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as member_count
            ', [Admin::SUPER_ADMIN, Admin::ADMIN, Admin::MEMBER])
            ->first();

        return [
            'super' => (int) ($counts->super_count ?? 0),
            'admin' => (int) ($counts->admin_count ?? 0),
            'member' => (int) ($counts->member_count ?? 0),
        ];
    }

    private function statsCards(bool $isSuperAdmin, array $counts): array
    {
        return [
            [
                'count' => $counts['users'],
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
                'count' => $counts['members'],
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
                'count' => $counts['domains'],
                'label' => 'No of Domains',
                'bg' => 'bg-orange-100',
                'stroke' => 'orange',
                'icon' => '<circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>',
                'visible' => true,
            ],
            [
                'count' => $counts['articles'],
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
                'count' => $counts['posts'],
                'label' => 'Post Campaigns',
                'bg' => 'bg-red-200',
                'stroke' => 'red',
                'icon' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>',
                'visible' => true,
            ],
            [
                'count' => $counts['schedule_posts'],
                'label' => 'Schedule Post Campaigns',
                'bg' => 'bg-[#8cdcff]',
                'stroke' => '#0094d4',
                'icon' => '<circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>',
                'visible' => true,
            ],
            [
                'count' => $counts['schedule_sticky'],
                'label' => 'Schedule Sticky Post Campaigns',
                'bg' => 'bg-[#b8e0ff]',
                'stroke' => '#0077b6',
                'icon' => '<circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>',
                'visible' => true,
            ],
            [
                'count' => $counts['schedule_sidebar'],
                'label' => 'Schedule Sidebar Campaigns',
                'bg' => 'bg-[#ffc7bd]',
                'stroke' => '#ff6e54',
                'icon' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="9" y1="3" x2="9" y2="21"></line>',
                'visible' => true,
            ],
        ];
    }
}
