<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use App\Models\Admin\Domain;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\{
    ScheduleSidebarCampaign,
    ScheduleSidebarCampaignDomain,
    ScheduleSidebarCampaignLink,
    ScheduleSidebarCampaignTask,
};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Str;

class ScheduleSidebarCampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        /* ============================
     | Validate URL inputs only
     ============================ */
        $request->validate([
            'search' => 'nullable|string|max:150',
            'status' => 'nullable|in:queued,running,paused,completed,failed',
            'from'   => 'nullable|date',
            'to'     => 'nullable|date',
        ]);

        /* ============================
     | Remove empty search from URL
     ============================ */
        if ($request->has('search') && trim($request->search) === '') {
            return redirect()->to(
                url()->current() . '?' . http_build_query(
                    $request->except('search')
                )
            );
        }

        $limit = 100;

        /* ============================
     | Base query (Scheduled Sidebar)
     ============================ */
        $query = ScheduleSidebarCampaign::query()
            ->with([
                'domains:id,schedule_sidebar_campaign_id',
                'links:id,schedule_sidebar_campaign_id',
                'domainCategory:id,name',
            ])
            ->withCount([
                'domains',
                'links',
            ]);

        /* ============================
     | Search: campaign_no
     ============================ */
        if ($request->filled('search')) {
            $query->where(
                'campaign_no',
                'LIKE',
                '%' . trim($request->search) . '%'
            );
        }

        /* ============================
     | Filter: status
     ============================ */
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        /* ============================
     | Filter: schedule date range
     ============================ */
        if ($request->filled('from')) {
            $query->whereDate(
                'schedule_from_date',
                '>=',
                $request->from
            );
        }

        if ($request->filled('to')) {
            $query->whereDate(
                'schedule_to_date',
                '<=',
                $request->to
            );
        }

        $admin = Auth::guard('admin')->user();
        if (!$admin->isSuperAdmin()) {
            $query->where('admin_id', $admin->id);
        }

        /* ============================
     | Pagination
     ============================ */
        $campaigns = $query
            ->orderByDesc('id')
            ->paginate($limit)
            ->appends($request->all());

        /* ============================
     | Offset for SNO in blade
     ============================ */
        $offset = ($campaigns->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-sidebar.schedule-sidebar-campaign',
            compact('campaigns', 'offset')
        );
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        // return view('admin.cam')

        $campaignId = 'SCH-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        // ** now providing user domain sets
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();

        $sites = Domain::all();

        return view('admin.campaigns.pbn-sidebar.create-schedule-sidebar-campaign', compact('campaignId', 'domainCategory', 'domainSets', 'sites'));
    }

    /**
     * Store a newly created resource in storage.
     */

    private function generateUniqueCampaignNo(string $input): string
    {
        // 1️⃣ Slugify (removes /, special chars, spaces)
        $base = Str::slug($input);

        // 2️⃣ Fallback if user enters garbage like /// or ###
        if ($base === '') {
            $base = 'campaign-' . now()->timestamp;
        }

        $slug = $base;
        $counter = 1;

        // 3️⃣ Ensure uniqueness
        while (ScheduleSidebarCampaign::where('campaign_no', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function store(Request $request)
    {
        // =====================================================
        // 1️⃣ VALIDATION
        // =====================================================
        $validated = $request->validate([
            'campaign_no'         => 'required|string',
            'domain_category_id'  => 'nullable|integer|exists:domain_categories,id',
            'sidebar_quantity'    => 'required|integer|min:1',

            'schedule_from_date'  => 'required|date',
            'schedule_to_date'    => 'required|date|after_or_equal:schedule_from_date',

            'keywordsDataHolder'  => 'required|string', // JSON
            'campaigns_domains'   => 'required|string', // JSON
        ]);

        // =====================================================
        // 2️⃣ NORMALIZE INPUT
        // =====================================================
        $qty = (int) $request->sidebar_quantity;

        $links = json_decode($request->keywordsDataHolder, true);
        if (!is_array($links)) {
            return back()->with('cus__error', 'Invalid links JSON')->withInput();
        }
        $links = array_values($links);

        $domainIds = json_decode($request->campaigns_domains, true);
        if (!is_array($domainIds)) {
            return back()->with('cus__error', 'Invalid domains JSON')->withInput();
        }
        $domainIds = array_values(array_map('intval', $domainIds));

        // =====================================================
        // 3️⃣ HARD COUNT CHECK
        // =====================================================
        if (count($links) !== $qty || count($domainIds) !== $qty) {
            return back()
                ->with('cus__error', 'Links and domains must match sidebar quantity')
                ->withInput();
        }

        // =====================================================
        // 4️⃣ DATE MATH (DAY-BASED)
        // =====================================================
        $from = Carbon::parse($request->schedule_from_date)->startOfDay();
        $to   = Carbon::parse($request->schedule_to_date)->startOfDay();

        $totalDays = $from->diffInDays($to) + 1;
        $perDay    = intdiv($qty, $totalDays);
        $remainder = $qty % $totalDays;

        // updated campaign no

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        // =====================================================
        // 5️⃣ TRANSACTION
        // =====================================================
        DB::transaction(function () use (
            $request,
            $campaignNo,
            $qty,
            $links,
            $domainIds,
            $from,
            $totalDays,
            $perDay,
            $remainder
        ) {

            // ---------------------------------------------
            // A) schedule_sidebar_campaigns
            // ---------------------------------------------
            $schedule = ScheduleSidebarCampaign::create([
                'campaign_no'        => $campaignNo,
                'admin_id'           => auth('admin')->id(),
                'domain_category_id' => $request->domain_category_id,
                'schedule_from_date' => $request->schedule_from_date,
                'schedule_to_date'   => $request->schedule_to_date,
                'status'             => 'queued',
                'total_targets'      => $qty,
                'completed_targets'  => 0,
                'failed_targets'     => 0,
            ]);

            // ---------------------------------------------
            // B) schedule_sidebar_campaign_domains
            // ---------------------------------------------
            $domainMap = [];
            foreach ($domainIds as $i => $domainId) {
                $row = ScheduleSidebarCampaignDomain::create([
                    'schedule_sidebar_campaign_id' => $schedule->id,
                    'domain_id'                    => $domainId,
                ]);
                $domainMap[$i] = $row->id;
            }

            // ---------------------------------------------
            // C) schedule_sidebar_campaign_links (OWN DATA)
            // ---------------------------------------------
            $linkMap = [];
            foreach ($links as $i => $row) {

                $link = ScheduleSidebarCampaignLink::create([
                    'schedule_sidebar_campaign_id' => $schedule->id,
                    'target_url'                   => trim($row['url']),
                    'anchor_keyword'               => trim($row['keyword']),
                    'nofollow'                     => !empty($row['nofollow']),
                    'sort_order'                   => $i + 1,
                ]);

                $linkMap[$i] = $link->id;
            }

            // ---------------------------------------------
            // D) schedule_sidebar_campaign_tasks
            // ---------------------------------------------
            $rows = [];
            $now  = now();

            for ($i = 0; $i < $qty; $i++) {

                if ($i < ($perDay + 1) * $remainder) {
                    $dayIndex = intdiv($i, $perDay + 1);
                } else {
                    $dayIndex = $remainder + intdiv(
                        $i - ($perDay + 1) * $remainder,
                        $perDay
                    );
                }

                $scheduleAt = $from
                    ->copy()
                    ->addDays($dayIndex)
                    ->setTime(0, 0);

                $rows[] = [
                    'schedule_sidebar_campaign_id'        => $schedule->id,
                    'schedule_sidebar_campaign_domain_id' => $domainMap[$i],
                    'schedule_sidebar_campaign_link_id'   => $linkMap[$i],
                    'schedule_at'                         => $scheduleAt,
                    'status'                              => 'queued',
                    'attempt_count'                       => 0,
                    'created_at'                          => $now,
                    'updated_at'                          => $now,
                ];

                if (count($rows) === 200) {
                    ScheduleSidebarCampaignTask::insert($rows);
                    $rows = [];
                }
            }

            if (!empty($rows)) {
                ScheduleSidebarCampaignTask::insert($rows);
            }
        });

        return redirect()
            ->route('admin.schedule.sidebar.campaign.create')
            ->with('cus__success', 'Scheduled sidebar campaign created successfully.');
    }



    /**
     * Display the specified resource.
     */
    public function report(string $campaign_no, string $token)
    {
        /* ============================
     | Validate scheduled campaign (PUBLIC)
     ============================ */
        $campaign = ScheduleSidebarCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        /* ============================
     | Aggregate stats (single source of truth)
     ============================ */
        $stats = ScheduleSidebarCampaignTask::where(
            'schedule_sidebar_campaign_id',
            $campaign->id
        )
            ->selectRaw("
            COUNT(*) as total,
            SUM(status = 'success') as success,
            SUM(status IN ('queued','publishing')) as queued,
            SUM(status = 'failed') as failed
        ")
            ->first();

        $successRate = $stats->total > 0
            ? round(($stats->success / $stats->total) * 100)
            : 0;

        /* ============================
     | Fetch tasks (report view, no pagination)
     ============================ */
        $tasks = ScheduleSidebarCampaignTask::with([
            'domain.domain',
            'link',
        ])
            ->where('schedule_sidebar_campaign_id', $campaign->id)
            ->get();
            // ->orderByDesc('id')

        /**
         * Scheduled sidebar campaigns:
         * - single anchor
         * - single URL
         */
        $keywordType = 'single';
        $maxKeywordCount = 1;

        return view(
            'admin.campaigns.pbn-sidebar.schedule-sidebar-campaign-report',
            compact(
                'campaign',
                'stats',
                'successRate',
                'tasks',
                'keywordType',
                'maxKeywordCount'
            )
        );
    }

    public function exportReport(string $campaign_no, string $token)
    {
        /* ============================
            | Validate scheduled sidebar campaign
            ============================ */
        $campaign = ScheduleSidebarCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        /* ============================
           | Fetch scheduled sidebar tasks
           ============================ */
        $tasks = ScheduleSidebarCampaignTask::with([
            'domain.domain', // ScheduleSidebarCampaignDomain → Domain
            'link',          // ScheduleSidebarCampaignLink
        ])
            ->where('schedule_sidebar_campaign_id', $campaign->id)
            ->get();
        //  ->orderBy('id')
        /* =========================================================
           | 1️⃣ DETECT IF NOFOLLOW EXISTS
           ========================================================= */
        $hasNofollow = $tasks->contains(function ($task) {
            return (bool) ($task->link?->nofollow ?? false);
        });

        /* =========================================================
           | 2️⃣ BUILD HEADERS (WITH SCHEDULE DATE)
           ========================================================= */
        $headers = [
            'S.No',
            'Domain',
            'Keyword',
            'URL',
            'Scheduled At',
        ];

        if ($hasNofollow) {
            $headers[] = 'Nofollow';
        }

        $headers[] = 'Status';
        $headers[] = 'Published Date';

        /* =========================================================
           | 3️⃣ CREATE EXCEL
           ========================================================= */
        $writer = SimpleExcelWriter::streamDownload(
            "schedule-sidebar-campaign-report-{$campaign_no}.xlsx"
        )->addHeader($headers);

        /* =========================================================
           | 4️⃣ FILL ROWS
           ========================================================= */
        $sno = 1;

        foreach ($tasks as $task) {

            $row = [
                'S.No'         => $sno++,
                'Domain'       => optional($task->domain?->domain)->name ?? '-',
                'Keyword'      => $task->link?->anchor_keyword ?? '-',
                'URL'          => $task->link?->target_url ?? '-',
                'Scheduled At' => optional($task->schedule_at)?->format('d M Y H:i') ?? '-',
            ];

            if ($hasNofollow) {
                $row['Nofollow'] = ($task->link?->nofollow ?? false)
                    ? 'No Follow'
                    : 'Follow';
            }

            $row['Status'] = $task->status === 'success'
                ? 'Live'
                : 'Not Live';

            // Sidebar links don’t really have "published_at"
            // Using created_at as report date (same logic as your old export)
            $row['Published Date'] = optional($task->created_at)?->format('d M Y') ?? '-';

            $writer->addRow($row);
        }

        return $writer->toBrowser();
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        /* ============================
           | Fetch Scheduled Sidebar Campaign
           ============================ */
        $campaign = ScheduleSidebarCampaign::with([
            'domains.domain', // schedule_sidebar_campaign_domains → domains
            'links',          // schedule_sidebar_campaign_links
        ])->findOrFail($id);

        /* ============================
           | Pagination
           ============================ */
        $limit = 100;

        /* ============================
           | Fetch Scheduled Sidebar Tasks
           ============================ */
        $campaignTasks = ScheduleSidebarCampaignTask::query()
            ->with([
                'domain.domain', // ScheduleSidebarCampaignDomain → Domain
                'link',          // ScheduleSidebarCampaignLink
            ])
            ->where('schedule_sidebar_campaign_id', $campaign->id)
            ->paginate($limit)
            ->withQueryString();

        //  ->orderByDesc('id')

        /* ============================
           | Offset for S.No
           ============================ */
        $offset = ($campaignTasks->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-sidebar.view-schedule-campaign',
            compact('campaign', 'campaignTasks', 'offset')
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
