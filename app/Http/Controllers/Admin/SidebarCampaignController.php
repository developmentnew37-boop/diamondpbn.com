<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Domain;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignDomain;
use App\Models\Admin\SidebarCampaignLink;
use App\Models\Admin\SidebarCampaignTask;
use Illuminate\Support\Facades\DB;
use App\Jobs\PublishSidebarBlogrollJob;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Str;

class SidebarCampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // ✅ Validate inputs
        $request->validate([
            'search' => 'nullable|string|max:150',
        ]);

        // ✅ Remove empty search from URL
        if ($request->has('search') && trim($request->search) === '') {
            return redirect()->to(
                url()->current() . '?' . http_build_query(
                    $request->except('search')
                )
            );
        }

        $limit = 20;

        // ✅ Sidebar campaigns base query
        $query = SidebarCampaign::query()
            ->with([
                'domains', // sidebar_campaign_domains
                'links',   // sidebar_campaign_links
                'tasks',   // sidebar_campaign_tasks
            ]);

        // 🔍 Search by campaign_no
        if ($request->filled('search')) {
            $query->where(
                'campaign_no',
                'LIKE',
                '%' . trim($request->search) . '%'
            );
        }
        $admin = Auth::guard('admin')->user();
        if (!$admin->isSuperAdmin()) {
            $query->where('admin_id', $admin->id);
        }
        // ✅ Paginate
        $campaigns = $query
            ->orderByDesc('id')
            ->paginate($limit)
            ->appends($request->all());

        // ✅ Offset for serial numbers
        $offset = ($campaigns->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-sidebar.sidebar-campaign',
            compact('campaigns', 'offset')
        );
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //

        $campaignId = 'SBC-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        // ** now providing user domain sets
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();

        $sites = Domain::all();

        return view('admin.campaigns.pbn-sidebar.create-sidebar-campaign', compact('campaignId', 'domainCategory', 'domainSets', 'sites'));
    }

    // ** //

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
        while (SidebarCampaign::where('campaign_no', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_no'         => 'required|string',
            'domain_category_id'  => 'nullable|integer|exists:domain_categories,id',
            'sidebar_quantity'    => 'required|integer|min:1',

            'keywordsDataHolder'  => 'required|string',
            'sel_domains'         => 'required|integer|in:0,1,2',
            'campaigns_domains'   => 'required|string',
        ]);

        $sidebarCount = (int) $request->sidebar_quantity;

        // ✅ links JSON -> array
        $links = json_decode((string) $request->keywordsDataHolder, true);
        if (!is_array($links)) {
            return back()->with('cus__error', 'Links data is invalid JSON.')->withInput();
        }

        // ✅ domains JSON -> array[int]
        $domainIds = json_decode((string) $request->campaigns_domains, true);
        if (!is_array($domainIds)) {
            return back()->with('cus__error', 'Domains data is invalid JSON.')->withInput();
        }
        $domainIds = array_values(array_filter(array_map('intval', $domainIds)));

        // ✅ hard rules
        if (count($links) !== $sidebarCount) {
            return back()->with('cus__error', "Links rows must be exactly {$sidebarCount}.")->withInput();
        }
        if (count($domainIds) !== $sidebarCount) {
            return back()->with('cus__error', "You must select exactly {$sidebarCount} domains.")->withInput();
        }

        // ✅ validate each link row has keyword + url
        foreach ($links as $i => $row) {
            $url = trim((string)($row['url'] ?? ''));
            $kw  = trim((string)($row['keyword'] ?? ''));

            if ($url === '' || $kw === '') {
                return back()->with(
                    'cus__error',
                    "Link row #" . ($i + 1) . " url/keyword cannot be empty."
                )->withInput();
            }
        }

        $domain__methods = ['random', 'domain_set', 'manual'];

        // updated campaign no

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        $campaign = DB::transaction(function () use (
            $request,
            $campaignNo,
            $sidebarCount,
            $links,
            $domainIds,
            $domain__methods
        ) {

            $now = now();

            // 1) master
            $campaign = SidebarCampaign::create([
                'campaign_no'        => $campaignNo,
                'domain_category_id' => $request->domain_category_id ?: null,
                'admin_id'           => auth('admin')->id(),

                'sidebar_count'      => $sidebarCount,
                'domain_method'      => $domain__methods[(int) $request->sel_domains],
                'status'             => 'queued',

                'total_targets'      => $sidebarCount,
                'completed_targets'  => 0,
                'failed_targets'     => 0,
            ]);

            // ==========================================================
            // 2) BULK INSERT LINKS (chunk)
            // ==========================================================
            $linkRows = [];
            foreach ($links as $idx => $row) {
                $linkRows[] = [
                    'sidebar_campaign_id' => $campaign->id,
                    'sort_order'          => $idx + 1,
                    'target_url'          => trim((string)$row['url']),
                    'anchor_keyword'      => trim((string)$row['keyword']),
                    'nofollow'            => !empty($row['nofollow']),
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }

            foreach (array_chunk($linkRows, 200) as $chunk) {
                SidebarCampaignLink::insert($chunk);
            }

            // Fetch inserted link IDs in correct pairing order (sort_order = 1..N)
            $linkIds = SidebarCampaignLink::where('sidebar_campaign_id', $campaign->id)
                ->orderBy('sort_order', 'asc')
                ->pluck('id')
                ->all();

            // ==========================================================
            // 3) BULK INSERT DOMAINS (chunk)
            // ==========================================================
            $domainRows = [];
            foreach ($domainIds as $idx => $domainId) {
                $domainRows[] = [
                    'sidebar_campaign_id' => $campaign->id,
                    'domain_id'           => $domainId,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }

            foreach (array_chunk($domainRows, 200) as $chunk) {
                SidebarCampaignDomain::insert($chunk);
            }

            // Fetch inserted domain row IDs (in insertion order for this campaign)
            $domainRowIds = SidebarCampaignDomain::where('sidebar_campaign_id', $campaign->id)
                ->orderBy('id', 'asc')
                ->pluck('id')
                ->all();

            // ✅ Safety: make sure we have same count (pairing will be exact)
            if (count($linkIds) !== $sidebarCount || count($domainRowIds) !== $sidebarCount) {
                throw new \RuntimeException("Mismatch after insert: links/domains count not equal to sidebarCount.");
            }

            // ==========================================================
            // 4) BULK INSERT TASKS (pair domain[i] with link[i]) (chunk)
            // ==========================================================
            $taskRows = [];
            for ($i = 0; $i < $sidebarCount; $i++) {
                $taskRows[] = [
                    'sidebar_campaign_id'        => $campaign->id,
                    'sidebar_campaign_domain_id' => $domainRowIds[$i],
                    'sidebar_campaign_link_id'   => $linkIds[$i], // ✅ required (fixes your “no default value” error)

                    'status'        => 'queued',
                    'attempt_count' => 0,
                    'max_attempts'  => 5,

                    // snapshot (single row)
                    'links_payload' => json_encode($links[$i], JSON_UNESCAPED_UNICODE),

                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            foreach (array_chunk($taskRows, 200) as $chunk) {
                SidebarCampaignTask::insert($chunk);
            }

            // Fetch task IDs for dispatch
            $taskIds = SidebarCampaignTask::where('sidebar_campaign_id', $campaign->id)
                ->orderBy('id', 'asc')
                ->pluck('id')
                ->all();

            // ✅ Dispatch jobs AFTER commit (also chunk dispatch to avoid burst)
            DB::afterCommit(function () use ($taskIds) {
                foreach (array_chunk($taskIds, 100) as $chunk) {
                    foreach ($chunk as $taskId) {
                        PublishSidebarBlogrollJob::dispatch($taskId)
                            ->onQueue('sidebar_campaigns');
                    }
                }
            });

            return $campaign;
        });

        return redirect()
            ->route('admin.sidebar.campaign.create')
            ->with(
                'cus__success',
                "Sidebar Campaign {$campaign->campaign_no} created successfully ({$campaign->total_targets} targets)."
            );
    }


    public function show(Request $request, string $id)
    {
        // ✅ Fetch campaign
        $campaign = SidebarCampaign::with([
            'domains.domain',
            'links',
        ])->findOrFail($id);

        // Pagination
        $limit = 100;

        // ✅ Fetch sidebar tasks (this is equivalent to CampaignPost)
        $campaignTasks = SidebarCampaignTask::query()
            ->with([
                'domainRow.domain',   // SidebarCampaignDomain → Domain
                'linkRow',            // SidebarCampaignLink
            ])
            ->where('sidebar_campaign_id', $campaign->id)
            ->orderByDesc('id')
            ->paginate($limit)
            ->withQueryString();

        // Offset
        $offset = ($campaignTasks->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-sidebar.view-campaign',
            compact('campaign', 'campaignTasks', 'offset')
        );
    }

    /* report page function */

    public function report(string $campaign_no, string $token)
    {
        // 1️⃣ Validate sidebar campaign via token (PUBLIC & SECURE)
        $campaign = SidebarCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        // 2️⃣ Aggregate stats (single source of truth)
        $stats = SidebarCampaignTask::where('sidebar_campaign_id', $campaign->id)
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

        // 3️⃣ Fetch sidebar tasks (report-style, no pagination)
        $tasks = SidebarCampaignTask::with([
            'domainRow.domain',
            'linkRow',
        ])
            ->where('sidebar_campaign_id', $campaign->id)
            ->orderByDesc('id')
            ->get();

        /**
         * Sidebar campaigns:
         * - Always single keyword
         * - Always single URL
         */
        $keywordType = 'single';
        $maxKeywordCount = 1;

        return view(
            'admin.campaigns.pbn-sidebar.sidebar-campaign-report',
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


    /** Report Excel file function **/


    // public function exportReport(string $campaign_no, string $token)
    // {
    //     // 🔐 Validate sidebar campaign (PUBLIC & SECURE)
    //     $campaign = SidebarCampaign::where('campaign_no', $campaign_no)
    //         ->where('report_token', $token)
    //         ->firstOrFail();

    //     // 📦 Fetch sidebar tasks
    //     $tasks = SidebarCampaignTask::with([
    //         'domainRow.domain',
    //         'linkRow',
    //     ])
    //         ->where('sidebar_campaign_id', $campaign->id)
    //         ->orderBy('id')
    //         ->get();

    //     /* =========================================================
    //    1️⃣ HEADERS (FIXED — SIDEBAR IS ALWAYS SINGLE)
    //    ========================================================= */
    //     $headers = [
    //         'S.No',
    //         'Domain',
    //         'Url',
    //         'Keyword',
    //         'Nofollow',
    //         'Status',
    //         'Date',
    //     ];

    //     /* =========================================================
    //    2️⃣ CREATE EXCEL
    //    ========================================================= */
    //     $writer = SimpleExcelWriter::streamDownload(
    //         "sidebar-campaign-report-{$campaign_no}.xlsx"
    //     )->addHeader($headers);

    //     /* =========================================================
    //    3️⃣ FILL ROWS
    //    ========================================================= */
    //     $sno = 1;

    //     foreach ($tasks as $task) {

    //         $row = [
    //             'S.No'       => $sno++,
    //             'Domain'     => optional($task->domainRow?->domain)->name ?? '-',
    //             'URL' => $task->linkRow?->target_url ?? '-',
    //             'Keyword'     => $task->linkRow?->anchor_keyword ?? '-',
    //             'Nofollow'   => ($task->linkRow?->nofollow ?? false) ? 'Yes' : 'No',
    //             'Status'     => $task->status === 'success' ? 'Live' : 'Not Live',
    //             'Date'       => optional($task->created_at)->format('d M Y'),
    //         ];

    //         $writer->addRow($row);
    //     }

    //     return $writer->toBrowser();
    // }

    public function exportReport(string $campaign_no, string $token)
    {
        // 🔐 Validate sidebar campaign
        $campaign = SidebarCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        // 📦 Fetch sidebar tasks
        $tasks = SidebarCampaignTask::with([
            'domainRow.domain',
            'linkRow',
        ])
            ->where('sidebar_campaign_id', $campaign->id)
            ->orderBy('id')
            ->get();

        /* =========================================================
       1️⃣ DETECT IF NOFOLLOW EXISTS
       ========================================================= */
        $hasNofollow = $tasks->contains(function ($task) {
            return (bool) ($task->linkRow?->nofollow ?? false);
        });

        /* =========================================================
       2️⃣ BUILD HEADERS (DYNAMIC)
       ========================================================= */
        $headers = [
            'S.No',
            'Domain',
            'Keyword',
            'URL',
        ];

        if ($hasNofollow) {
            $headers[] = 'Nofollow';
        }

        $headers[] = 'Status';
        $headers[] = 'Date';

        /* =========================================================
       3️⃣ CREATE EXCEL
       ========================================================= */
        $writer = SimpleExcelWriter::streamDownload(
            "sidebar-campaign-report-{$campaign_no}.xlsx"
        )->addHeader($headers);

        /* =========================================================
       4️⃣ FILL ROWS
       ========================================================= */
        $sno = 1;

        foreach ($tasks as $task) {

            $row = [
                'S.No'       => $sno++,
                'Domain'     => optional($task->domainRow?->domain)->name ?? '-',
                'Keyword'     => $task->linkRow?->anchor_keyword ?? '-',
                'URL' => $task->linkRow?->target_url ?? '-',
            ];

            if ($hasNofollow) {
                $row['Nofollow'] = ($task->linkRow?->nofollow ?? false)
                    ? 'No Follow'
                    : 'Follow';
            }

            $row['Status'] = $task->status === 'success'
                ? 'Live'
                : 'Not Live';

            $row['Date'] = optional($task->created_at)->format('d M Y');

            $writer->addRow($row);
        }

        return $writer->toBrowser();
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
