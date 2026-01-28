<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Domain;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\HiddenLinksCampaignDomains;
use App\Models\Admin\HiddenLinksCampaignLinks;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Models\Admin\HiddenLinksCampaign;
use App\Jobs\PublishHiddenLinksJob;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Str;

class HiddenLinkCampaignController extends Controller
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

        $query = HiddenLinksCampaign::query()
            ->with('domainCategory')
            ->withCount([
                'links',
                'domains',
                // optional future use
                'tasks as total_tasks',
                'tasks as success_tasks' => fn($q) => $q->where('status', 'success'),
                'tasks as failed_tasks'  => fn($q) => $q->where('status', 'failed'),
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

        $campaigns = $query
            ->orderByDesc('id')
            ->paginate($limit)
            ->appends($request->all());

        $offset = ($campaigns->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-hidden-links.hidden-links-campaign',
            compact('campaigns', 'offset')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $campaignId = 'HLC-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        // ** now providing user domain sets
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();

        $sites = Domain::all();

        return view('admin.campaigns.pbn-hidden-links.create-hidden-links-campaign', compact('campaignId', 'domainCategory', 'domainSets', 'sites'));
    }

    /**
     * Store a newly created resource in storage.
     */

    // ** unique campaign no */

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
        while (HiddenLinksCampaign::where('campaign_no', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }



    public function store(Request $request)
    {
        // =====================================================
        // 1) Validate input
        // =====================================================
        $validated = $request->validate([
            'campaign_no'         => 'required|string',
            'domain_category_id'  => 'nullable|integer|exists:domain_categories,id',
            'sidebar_quantity'    => 'required|integer|min:1',

            'keywordsDataHolder'  => 'required|string', // JSON
            'sel_domains'         => 'required|integer|in:0,1,2',
            'campaigns_domains'   => 'required|string', // JSON
        ]);

        $sidebarCount = (int) $request->sidebar_quantity;

        // =====================================================
        // 2) Decode + validate links
        // =====================================================
        $links = json_decode((string) $request->keywordsDataHolder, true);
        if (!is_array($links)) {
            return back()->with('cus__error', 'Links data is invalid JSON.')->withInput();
        }

        // =====================================================
        // 3) Decode + validate domains
        // =====================================================
        $domainIds = json_decode((string) $request->campaigns_domains, true);
        if (!is_array($domainIds)) {
            return back()->with('cus__error', 'Domains data is invalid JSON.')->withInput();
        }

        $domainIds = array_values(array_filter(array_map('intval', $domainIds)));

        // =====================================================
        // 4) Hard rules (COUNT MUST MATCH)
        // =====================================================
        if (count($links) !== $sidebarCount) {
            return back()->with('cus__error', "Links rows must be exactly {$sidebarCount}.")->withInput();
        }

        if (count($domainIds) !== $sidebarCount) {
            return back()->with('cus__error', "You must select exactly {$sidebarCount} domains.")->withInput();
        }

        // =====================================================
        // 5) Validate each link row
        // =====================================================
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

        $domainMethods = ['random', 'domain_set', 'manual'];

        // updated campaign no

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        // =====================================================
        // 6) Atomic DB transaction
        // =====================================================
        $campaign = DB::transaction(function () use (
            $request,
            $campaignNo,
            $sidebarCount,
            $links,
            $domainIds,
            $domainMethods
        ) {

            // -----------------------------------------
            // A) Create campaign
            // -----------------------------------------
            $campaign = HiddenLinksCampaign::create([
                'campaign_no'        => $campaignNo,
                'domain_category_id' => $request->domain_category_id ?: null,
                'admin_id'           => auth('admin')->id(),

                'sidebar_count'      => $sidebarCount,
                'domain_method'      => $domainMethods[(int)$request->sel_domains],
                'status'             => 'queued',

                'total_targets'      => $sidebarCount,
                'completed_targets'  => 0,
                'failed_targets'     => 0,
            ]);

            // -----------------------------------------
            // B) Insert LINKS (keep IDs by index)
            // -----------------------------------------
            $linkIds = [];

            foreach ($links as $idx => $row) {
                $link = HiddenLinksCampaignLinks::create([
                    'hidden_links_campaigns_id' => $campaign->id,
                    'sort_order'                => $idx + 1,
                    'target_url'                => trim($row['url']),
                    'anchor_keyword'            => trim($row['keyword']),
                    'nofollow'                  => !empty($row['nofollow']),
                ]);

                $linkIds[$idx] = $link->id;
            }

            // -----------------------------------------
            // C) Insert DOMAINS (keep IDs by index)
            // -----------------------------------------
            $domainRowIds = [];

            foreach ($domainIds as $idx => $domainId) {
                $row = HiddenLinksCampaignDomains::create([
                    'hidden_links_campaigns_id' => $campaign->id,
                    'domain_id'                 => $domainId,
                ]);

                $domainRowIds[$idx] = $row->id;
            }

            // -----------------------------------------
            // D) Create TASKS (domain[i] ↔ link[i])
            // -----------------------------------------
            $taskIds = [];

            for ($i = 0; $i < $sidebarCount; $i++) {
                $task = HiddenLinksCampaignTasks::create([
                    'hidden_links_campaigns_id'        => $campaign->id,
                    'hidden_links_campaigns_domain_id' => $domainRowIds[$i],
                    'hidden_links_campaigns_link_id'   => $linkIds[$i],

                    'status'        => 'queued',
                    'attempt_count' => 0,
                    'max_attempts'  => 5,

                    // snapshot (single link for audit/debug)
                    'links_payload' => json_encode($links[$i], JSON_UNESCAPED_UNICODE),
                ]);

                $taskIds[] = $task->id;
            }

            // -----------------------------------------
            // E) Dispatch jobs AFTER commit
            // -----------------------------------------
            DB::afterCommit(function () use ($taskIds) {
                foreach ($taskIds as $taskId) {
                    PublishHiddenLinksJob::dispatch($taskId)
                        ->onQueue('hidden_links_campaigns');
                }
            });

            return $campaign;
        });

        // =====================================================
        // 7) Redirect
        // =====================================================
        return redirect()
            ->route('admin.hidden.link.campaign.create')
            ->with(
                'cus__success',
                "Hidden Links Campaign {$campaign->campaign_no} created successfully ({$campaign->total_targets} tasks)."
            );
    }


    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $campaign = HiddenLinksCampaign::query()
            ->with('domainCategory')
            ->findOrFail($id);

        $limit = 20;

        $campaignTasks = HiddenLinksCampaignTasks::query()
            ->where('hidden_links_campaigns_id', $campaign->id)
            ->with([
                'domainRow.domain',
                'linkRow',
            ])
            ->orderByDesc('id')
            ->paginate($limit)
            ->appends($request->all());

        $offset = ($campaignTasks->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-hidden-links.view-campaign',
            compact('campaign', 'campaignTasks', 'offset')
        );
    }

    /**
     * This is the report page function.
     */

    public function report(string $campaign_no, string $token)
    {
        // 1️⃣ Validate hidden links campaign via token (PUBLIC & SECURE)
        $campaign = HiddenLinksCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        // 2️⃣ Aggregate stats
        $stats = HiddenLinksCampaignTasks::where(
            'hidden_links_campaigns_id',
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

        // 3️⃣ Fetch all tasks (report style, no pagination)
        $tasks = HiddenLinksCampaignTasks::with([
            'domainRow.domain',
            'linkRow',
        ])
            ->where('hidden_links_campaigns_id', $campaign->id)
            ->orderByDesc('id')
            ->get();

        return view(
            'admin.campaigns.pbn-hidden-links.hidden-link-campaign-report',
            compact(
                'campaign',
                'stats',
                'successRate',
                'tasks'
            )
        );
    }

    /**
     * This is the export report function.
     */

    public function exportReport(string $campaign_no, string $token)
    {
        // 🔐 1️⃣ Validate hidden links campaign (PUBLIC & SECURE)
        $campaign = HiddenLinksCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        // 📦 2️⃣ Fetch hidden links tasks
        $tasks = HiddenLinksCampaignTasks::with([
            'domainRow.domain',
            'linkRow',
        ])
            ->where('hidden_links_campaigns_id', $campaign->id)
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
            'Live Link',
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
            "hidden-links-campaign-report-{$campaign_no}.xlsx"
        )->addHeader($headers);

        /* =========================================================
       4️⃣ FILL ROWS
       ========================================================= */
        $sno = 1;

        foreach ($tasks as $task) {

            $row = [
                'S.No'       => $sno++,
                'Live Link'     => $task->domainRow?->domain?->name ?? '-',
                'Domain'     => $task->domainRow?->domain?->name ?? '-',
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
