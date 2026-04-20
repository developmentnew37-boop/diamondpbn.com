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
use App\Jobs\BulkUpdateSidebarBlogrollJob;
use App\Http\Controllers\Admin\Concerns\AppliesSuperAdminCampaignOwnerFilter;
use App\Http\Controllers\Admin\Concerns\AuthorizesAdminCampaign;
use App\Http\Controllers\Admin\Concerns\ValidatesBulkCampaignIds;
use App\Jobs\DeleteSidebarCampaignJob;
use App\Services\PurgeLocalCampaignDataService;
use App\Jobs\PublishSidebarBlogrollJob;
use App\Services\BlogrollApiService;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Str;

class SidebarCampaignController extends Controller
{
    use AppliesSuperAdminCampaignOwnerFilter;
    use AuthorizesAdminCampaign;
    use ValidatesBulkCampaignIds;

    public function __construct()
    {
        $this->middleware('can.create.campaigns')->except(['report', 'exportReport']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // ✅ Validate inputs
        $request->validate([
            'search' => 'nullable|string|max:150',
            'filter_user' => 'nullable|string|max:20',
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
        $search = trim((string) $request->input('search', ''));

        // ✅ Sidebar campaigns base query
        $query = SidebarCampaign::query()
            ->select([
                'id',
                'campaign_no',
                'domain_category_id',
                'admin_id',
                'total_targets',
                'completed_targets',
                'failed_targets',
                'last_bulk_updated_at',
                'created_at',
                'report_token',
            ])
            ->with(['domainCategory:id,name'])
            ->withCount([
                'domains',
                'links',
            ]);

        // 🔍 Search by campaign_no
        if ($search !== '') {
            $query->where(
                'campaign_no',
                'LIKE',
                '%' . $search . '%'
            );
        }
        $admin = Auth::guard('admin')->user();
        $ownerData = $this->scopeCampaignQueryForOwner($query, $request, $admin);
        // ✅ Paginate
        $campaigns = $query
            ->orderByDesc('id')
            ->simplePaginate($limit)
            ->appends($request->query());

        // ✅ Offset for serial numbers
        $offset = ($campaigns->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-sidebar.sidebar-campaign',
            array_merge(compact('campaigns', 'offset'), $ownerData)
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
        // ✅ Fetch only fields needed by this page
        $campaign = SidebarCampaign::query()
            ->select(['id', 'campaign_no', 'last_bulk_updated_at'])
            ->findOrFail($id);

        // Pagination
        $limit = 100;

        // ✅ Fetch sidebar tasks (this is equivalent to CampaignPost)
        $campaignTasks = SidebarCampaignTask::query()
            ->select([
                'id',
                'sidebar_campaign_id',
                'sidebar_campaign_domain_id',
                'sidebar_campaign_link_id',
                'status',
                'remote_id',
                'remote_url',
                'attempt_count',
                'last_error',
                'next_retry_at',
                'content_updated_at',
                'created_at',
            ])
            ->with([
                'domainRow.domain',   // SidebarCampaignDomain → Domain
                'linkRow',            // SidebarCampaignLink
            ])
            ->where('sidebar_campaign_id', $campaign->id)
            ->orderByDesc('id')
            ->paginate($limit)
            ->withQueryString();

        $hasPublishedLinks = SidebarCampaignTask::query()
            ->where('sidebar_campaign_id', $campaign->id)
            ->where('status', 'success')
            ->whereNotNull('remote_id')
            ->exists();

        // Offset
        $offset = ($campaignTasks->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-sidebar.view-campaign',
            compact('campaign', 'campaignTasks', 'offset', 'hasPublishedLinks')
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
        $tasks = SidebarCampaignTask::query()
            ->select([
                'id',
                'sidebar_campaign_id',
                'sidebar_campaign_domain_id',
                'sidebar_campaign_link_id',
                'status',
                'created_at',
            ])
            ->with([
                'domainRow:id,sidebar_campaign_id,domain_id',
                'domainRow.domain:id,name',
                'linkRow:id,sidebar_campaign_id,target_url,anchor_keyword,nofollow',
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
        $baseTaskQuery = SidebarCampaignTask::query()
            ->where('sidebar_campaign_id', $campaign->id);

        /* =========================================================
       1️⃣ DETECT IF NOFOLLOW EXISTS
       ========================================================= */
        $hasNofollow = (clone $baseTaskQuery)
            ->whereHas('linkRow', fn ($q) => $q->where('nofollow', true))
            ->exists();

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
        (clone $baseTaskQuery)
            ->select([
                'id',
                'sidebar_campaign_id',
                'sidebar_campaign_domain_id',
                'sidebar_campaign_link_id',
                'status',
                'created_at',
            ])
            ->with([
                'domainRow:id,sidebar_campaign_id,domain_id',
                'domainRow.domain:id,name',
                'linkRow:id,sidebar_campaign_id,target_url,anchor_keyword,nofollow',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($tasks) use (&$sno, $hasNofollow, $writer) {
                foreach ($tasks as $task) {
                    $row = [
                        'S.No'    => $sno++,
                        'Domain'  => optional($task->domainRow?->domain)->name ?? '-',
                        'Keyword' => $task->linkRow?->anchor_keyword ?? '-',
                        'URL'     => $task->linkRow?->target_url ?? '-',
                    ];

                    if ($hasNofollow) {
                        $row['Nofollow'] = ($task->linkRow?->nofollow ?? false)
                            ? 'No Follow'
                            : 'Follow';
                    }

                    $row['Status'] = $task->status === 'success' ? 'Live' : 'Not Live';
                    $row['Date'] = optional($task->created_at)->format('d M Y');
                    $writer->addRow($row);
                }
            }, 'id');

        return $writer->toBrowser();
    }

    /**
     * Manual retry sidebar task: allow queued/failed/publishing; reset attempts and run from first.
     */
    public function retryTask(string $id)
    {
        $task = SidebarCampaignTask::with('campaign')->find($id);

        if (!$task) {
            return back()->with('cus__error', 'Task not found');
        }

        if ($task->status === 'success') {
            return back()->with('cus__error', 'Successful tasks do not need retry');
        }

        $campaign = $task->campaign;
        if ($campaign && in_array($campaign->status, ['paused', 'cancelled'], true)) {
            return back()->with('cus__error', 'Cannot retry: campaign is paused or cancelled');
        }

        $task->update([
            'status'        => 'queued',
            'attempt_count' => 0,
            'next_retry_at' => null,
            'locked_at'     => null,
            'lock_token'    => null,
            'last_error'    => null,
        ]);

        PublishSidebarBlogrollJob::dispatch($task->id)->onQueue('sidebar_campaigns');

        return back()->with('cus__success', 'Sidebar task retry queued and will run from first.');
    }

    /**
     * Update sidebar entry on remote and in DB: fetch blogroll by api_key (GET), find index by remote_id, PATCH update/{index} with api_key in body.
     */
    public function updateSidebarTask(Request $request, string $id)
    {
        $task = SidebarCampaignTask::with(['domainRow.domain', 'linkRow'])->find($id);

        if (!$task || !$task->linkRow) {
            return back()->with('cus__error', 'Task or link not found');
        }

        if (!$task->remote_id) {
            return back()->with('cus__error', 'Task has no remote_id; cannot update on remote.');
        }

        $domain = $task->domainRow?->domain;
        if (!$domain || !$domain->api_key) {
            return back()->with('cus__error', 'Domain or API key missing');
        }

        $request->validate([
            'keyword' => 'required|string|max:500',
            'link'    => 'required|url|max:500',
        ]);

        $keyword = trim($request->keyword);
        $link    = trim($request->link);

        $res = BlogrollApiService::updateEntryByRemoteId($domain->name, $domain->api_key, $task->remote_id, $keyword, $link);
        if (!$res->successful()) {
            return back()->with('cus__error', 'Remote update failed: ' . $res->body());
        }

        $task->linkRow->update([
            'anchor_keyword' => $keyword,
            'target_url'     => $link,
        ]);
        $task->update(['content_updated_at' => now()]);

        return back()->with('cus__success', 'Sidebar link updated on remote and in database.');
    }

    /**
     * Single delete: remove one sidebar task from remote (if published) and DB; decrement campaign total_targets, completed_targets or failed_targets.
     */
    public function deleteSidebarTask(string $id)
    {
        $task = SidebarCampaignTask::with(['domainRow.domain', 'linkRow', 'campaign'])->find($id);

        if (!$task) {
            return back()->with('cus__error', 'Task not found.');
        }

        $campaign = $task->campaign;
        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found.');
        }

        $postStatus = $task->status;

        if ($task->remote_id) {
            $domain = $task->domainRow?->domain;
            if ($domain && $domain->api_key) {
                $res = BlogrollApiService::deleteEntryByRemoteId($domain->name, $domain->api_key, $task->remote_id);
                if (!$res->successful()) {
                    return back()->with('cus__error', 'Remote delete failed: ' . $res->body());
                }
            }
        }

        if ($campaign->total_targets > 0) {
            $campaign->decrement('total_targets');
        }
        if ($postStatus === 'success' && $campaign->completed_targets > 0) {
            $campaign->decrement('completed_targets');
        } elseif ($postStatus === 'failed' && $campaign->failed_targets > 0) {
            $campaign->decrement('failed_targets');
        }

        $linkId      = $task->sidebar_campaign_link_id;
        $domainRowId = $task->sidebar_campaign_domain_id;
        $task->delete();
        if ($linkId) {
            SidebarCampaignLink::where('id', $linkId)->delete();
        }
        if ($domainRowId) {
            SidebarCampaignDomain::where('id', $domainRowId)->delete();
        }

        return back()->with('cus__success', 'Sidebar link removed from remote and database.');
    }

    /**
     * Show form to edit sidebar task keyword/link (for tasks with remote_id).
     */
    public function editSidebarTask(string $id)
    {
        $task = SidebarCampaignTask::with(['domainRow.domain', 'linkRow', 'campaign'])->find($id);

        if (!$task) {
            return back()->with('cus__error', 'Task not found');
        }

        if (!$task->remote_id) {
            return back()->with('cus__error', 'Task has no remote_id; only published entries can be updated.');
        }

        return view('admin.campaigns.pbn-sidebar.edit-sidebar-task', compact('task'));
    }

    /**
     * Queue sidebar campaign deletion: remote blogroll deletes + DB cleanup run in background (DeleteSidebarCampaignJob).
     */
    public function destroy(string $id)
    {
        $campaign = SidebarCampaign::find($id);

        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        DeleteSidebarCampaignJob::dispatch($campaign->id)->onQueue('sidebar_deletions');

        return redirect()
            ->route('admin.sidebar.campaign.index')
            ->with('cus__success', 'Sidebar campaign deletion queued. Links will be removed from remote sites and the database in the background. Run the queue worker to process it.');
    }

    /**
     * Remove campaign data from this application only. Remote blogroll entries are not deleted.
     */
    public function purgeLocalOnly(string $id)
    {
        $campaign = SidebarCampaign::find($id);
        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }
        $this->authorizeCampaignAccess($campaign);
        PurgeLocalCampaignDataService::purgeSidebarCampaign((int) $campaign->id);

        return redirect()
            ->route('admin.sidebar.campaign.index')
            ->with(
                'cus__success',
                'Campaign removed from this dashboard only. Remote blogroll links were not deleted.'
            );
    }

    /**
     * Remove multiple sidebar campaigns from the database only (no remote API calls).
     */
    public function bulkPurgeLocal(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return back()->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, SidebarCampaign::class);
        if ($allowed === []) {
            return back()->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        foreach ($allowed as $id) {
            PurgeLocalCampaignDataService::purgeSidebarCampaign($id);
        }

        $n = count($allowed);

        return redirect()
            ->route('admin.sidebar.campaign.index')
            ->with('cus__success', $n . ' campaign(s) removed from this dashboard only. Remote blogroll links were not deleted.');
    }

    /**
     * Show the form for editing the specified resource (bulk edit links by batch).
     * Batches = distinct (keyword, url): updating a batch updates all remote sites that have that same keyword+url.
     */
    public function edit(string $id)
    {
        $campaign = SidebarCampaign::with(['links', 'domains'])->findOrFail($id);
        $this->authorizeCampaignAccess($campaign);

        $links = SidebarCampaignLink::where('sidebar_campaign_id', $campaign->id)->get();

        $batches = [];
        foreach ($links as $link) {
            $k = trim((string) ($link->anchor_keyword ?? ''));
            $u = trim((string) ($link->target_url ?? ''));
            $key = $k . "\n" . $u;

            if (!isset($batches[$key])) {
                $batches[$key] = [
                    'representative_link_id' => $link->id,
                    'keyword'                => $k,
                    'url'                    => $u,
                    'link_ids'               => [],
                ];
            }
            $batches[$key]['link_ids'][] = $link->id;
        }

        $publishedCountsByLinkId = SidebarCampaignTask::query()
            ->where('sidebar_campaign_id', $campaign->id)
            ->whereNotNull('remote_id')
            ->where('status', 'success')
            ->selectRaw('sidebar_campaign_link_id, COUNT(*) as c')
            ->groupBy('sidebar_campaign_link_id')
            ->pluck('c', 'sidebar_campaign_link_id');

        foreach ($batches as &$batch) {
            $batch['count'] = array_sum(array_map(
                fn ($linkId) => (int) ($publishedCountsByLinkId[$linkId] ?? 0),
                $batch['link_ids']
            ));
            unset($batch['link_ids']);
        }
        unset($batch);

        $distinctBatches = array_values(array_filter($batches, fn ($b) => $b['count'] > 0));
        $allLinksForBulk = SidebarCampaignLink::where('sidebar_campaign_id', $campaign->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'anchor_keyword', 'target_url'])
            ->map(fn ($link) => [
                'link_id' => (int) $link->id,
                'keyword' => trim((string) ($link->anchor_keyword ?? '')),
                'url' => trim((string) ($link->target_url ?? '')),
            ])
            ->all();

        return view(
            'admin.campaigns.pbn-sidebar.edit-sidebar-campaign',
            compact('campaign', 'distinctBatches', 'allLinksForBulk')
        );
    }

    /**
     * Bulk update sidebar links by batch (same keyword+url = one batch). Update one batch = update all remote sites
     * that have that keyword+url. Queued per batch for background processing.
     */
    public function update(Request $request, string $id)
    {
        $campaign = SidebarCampaign::findOrFail($id);
        if ($request->input('edit_kw_tab') === 'campaign') {
            $validated = $request->validate([
                'campaign_no' => 'required|string|max:191',
            ]);
            $raw = trim((string) $validated['campaign_no']);
            if ($raw === '') {
                return back()->with('cus__error', 'Campaign title is required.')
                    ->with('edit_sidebar_campaign_tab', 'campaign');
            }

            $base = Str::slug($raw);
            if ($base === '') {
                $base = 'campaign-' . now()->timestamp;
            }
            $slug = $base;
            $counter = 1;
            while (
                SidebarCampaign::where('campaign_no', $slug)
                    ->where('id', '!=', $campaign->id)
                    ->exists()
            ) {
                $slug = "{$base}-{$counter}";
                $counter++;
            }

            $campaign->update(['campaign_no' => $slug]);

            return redirect()
                ->route('admin.sidebar.campaign.edit', $campaign->id)
                ->with('cus__success', 'Campaign title updated.')
                ->with('edit_sidebar_campaign_tab', 'campaign');
        }

        $representativeLinkIds = $request->input('batch_representative_link_id', []);

        if (!is_array($representativeLinkIds)) {
            $representativeLinkIds = [];
        }

        $isBulkTextarea = $request->exists('bulk_urls') && $request->exists('bulk_keywords');
        if ($isBulkTextarea) {
            $expected = count($representativeLinkIds);
            $batchUrls = $this->parseManualLinesStrict((string) $request->input('bulk_urls', ''), $expected);
            $batchKeywords = $this->parseManualLinesStrict((string) $request->input('bulk_keywords', ''), $expected);
            if ($batchUrls === null || $batchKeywords === null) {
                return redirect()
                    ->route('admin.sidebar.campaign.edit', $campaign->id)
                    ->with('cus__error', 'Bulk URLs and Bulk Keywords must each have exactly ' . $expected . ' non-empty lines.')
                    ->withInput($request->only(['bulk_urls', 'bulk_keywords']))
                    ->with(
                        'edit_sidebar_campaign_tab',
                        in_array($request->input('edit_kw_tab'), ['normal', 'bulk'], true)
                            ? $request->input('edit_kw_tab')
                            : 'bulk'
                    );
            }
        } else {
            $batchKeywords = $request->input('batch_keyword', []);
            $batchUrls = $request->input('batch_url', []);
            $batchKeywords = is_array($batchKeywords) ? array_values($batchKeywords) : [];
            $batchUrls = is_array($batchUrls) ? array_values($batchUrls) : [];
        }

        $updates       = [];
        $queuedBatches = 0;

        foreach ($representativeLinkIds as $index => $repLinkId) {
            $repLinkId = (int) $repLinkId;
            $representative = SidebarCampaignLink::where('sidebar_campaign_id', $campaign->id)->find($repLinkId);
            if (!$representative) {
                continue;
            }

            $newKeyword = Str::limit(trim((string) ($batchKeywords[$index] ?? '')), 500, '');
            $newUrl     = Str::limit(trim((string) ($batchUrls[$index] ?? '')), 500, '');

            if ($newKeyword === '' || $newUrl === '') {
                continue;
            }

            $oldKeyword = trim((string) ($representative->anchor_keyword ?? ''));
            $oldUrl     = trim((string) ($representative->target_url ?? ''));

            if ($oldKeyword === $newKeyword && $oldUrl === $newUrl) {
                continue;
            }

            $linkIds = $isBulkTextarea
                ? [$representative->id]
                : SidebarCampaignLink::where('sidebar_campaign_id', $campaign->id)
                    ->where('anchor_keyword', $representative->anchor_keyword)
                    ->where('target_url', $representative->target_url)
                    ->pluck('id')
                    ->all();

            $tasks = SidebarCampaignTask::with(['domainRow.domain', 'linkRow'])
                ->where('sidebar_campaign_id', $campaign->id)
                ->whereIn('sidebar_campaign_link_id', $linkIds)
                ->whereNotNull('remote_id')
                ->where('status', 'success')
                ->get();

            foreach ($tasks as $task) {
                $domain = $task->domainRow?->domain;
                if (!$domain || !$domain->api_key) {
                    continue;
                }
                $updates[] = [
                    'task_id' => $task->id,
                    'keyword' => $newKeyword,
                    'link'    => $newUrl,
                ];
            }

            if (count($tasks) > 0) {
                SidebarCampaignLink::whereIn('id', $linkIds)->update([
                    'anchor_keyword' => $newKeyword,
                    'target_url'     => $newUrl,
                ]);
                $queuedBatches++;
            }
        }

        if (count($updates) === 0) {
            return redirect()
                ->route('admin.sidebar.campaign.edit', $campaign->id)
                ->with('cus__error', 'No changes to apply or no valid batches (keyword and URL required).')
                ->with(
                    'edit_sidebar_campaign_tab',
                    in_array($request->input('edit_kw_tab'), ['normal', 'bulk'], true)
                        ? $request->input('edit_kw_tab')
                        : 'normal'
                )
                ->withInput($request->only(['bulk_urls', 'bulk_keywords']));
        }

        $campaign->update(['last_bulk_updated_at' => now()]);

        $taskIdsToMark = array_unique(array_column($updates, 'task_id'));
        SidebarCampaignTask::whereIn('id', $taskIdsToMark)->update(['content_updated_at' => now()]);

        $batchSize = (int) config('sidebar.bulk_update_batch_size', 20);
        $batchSize = $batchSize > 0 ? $batchSize : 20;
        $chunks   = array_chunk($updates, $batchSize);
        $queued   = 0;

        foreach ($chunks as $chunk) {
            BulkUpdateSidebarBlogrollJob::dispatch($chunk)->onQueue('bulk_blogroll_updates');
            $queued++;
        }

        $msg = count($updates) . ' link(s) across ' . $queuedBatches . ' batch(es) queued for remote update. ';

        return redirect()
            ->route('admin.sidebar.campaign.show', $campaign->id)
            ->with('cus__success', $msg);
    }

    /**
     * Bulk delete selected sidebar tasks: delete on remote (blogroll API) then in DB; decrement campaign counts.
     */
    public function bulkDeleteTasks(Request $request, string $id)
    {
        $campaign = SidebarCampaign::findOrFail($id);

        $taskIds = $request->input('task_ids', []);
        if (!is_array($taskIds)) {
            $taskIds = [];
        }
        $taskIds = array_values(array_filter(array_map('intval', $taskIds)));

        if (count($taskIds) === 0) {
            return back()->with('cus__error', 'No tasks selected.');
        }

        $tasks = SidebarCampaignTask::with(['domainRow.domain', 'linkRow'])
            ->where('sidebar_campaign_id', $campaign->id)
            ->whereIn('id', $taskIds)
            ->get();

        $deletedRemote = 0;
        $errors = [];

        foreach ($tasks as $task) {
            if ($task->remote_id) {
                $domain = $task->domainRow?->domain;
                if ($domain && $domain->api_key) {
                    $res = BlogrollApiService::deleteEntryByRemoteId($domain->name, $domain->api_key, $task->remote_id);
                    if ($res->successful()) {
                        $deletedRemote++;
                    } else {
                        $errors[] = optional($domain)->name . ': ' . $res->body();
                    }
                }
            }

            if ($campaign->total_targets > 0) {
                $campaign->decrement('total_targets');
            }
            if ($task->status === 'success' && $campaign->completed_targets > 0) {
                $campaign->decrement('completed_targets');
            } elseif ($task->status === 'failed' && $campaign->failed_targets > 0) {
                $campaign->decrement('failed_targets');
            }

            $linkId = $task->sidebar_campaign_link_id;
            $domainRowId = $task->sidebar_campaign_domain_id;
            $task->delete();
            if ($linkId) {
                SidebarCampaignLink::where('id', $linkId)->delete();
            }
            if ($domainRowId) {
                SidebarCampaignDomain::where('id', $domainRowId)->delete();
            }
        }

        if (count($errors) > 0) {
            return back()->with('cus__error', 'Some remote deletes failed: ' . implode(' ', $errors))
                ->with('cus__success', $deletedRemote > 0 ? "{$deletedRemote} link(s) removed from remote; all selected tasks removed from database." : null);
        }

        return back()->with('cus__success', 'Selected links deleted from remote and database.');
    }

    private function parseManualLinesStrict(string $text, int $expectedCount): ?array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $lines = array_map(static fn ($line) => trim((string) $line), $lines ?: []);
        $lines = array_values(array_filter($lines, static fn ($line) => $line !== ''));
        if (count($lines) !== $expectedCount) {
            return null;
        }
        return $lines;
    }
}
