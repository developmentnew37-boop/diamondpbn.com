<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AppliesCampaignListStatusFilter;
use App\Http\Controllers\Admin\Concerns\AppliesSuperAdminCampaignOwnerFilter;
use App\Http\Controllers\Admin\Concerns\AuthorizesAdminCampaign;
use App\Http\Controllers\Admin\Concerns\ProvidesLocalClientsForForms;
use App\Http\Controllers\Admin\Concerns\ValidatesBulkCampaignIds;
use App\Http\Controllers\Controller;
use App\Jobs\BulkDeleteHiddenLinkCampaignsJob;
use App\Jobs\BulkDeleteHiddenLinksJob;
use App\Jobs\BulkRetryHiddenLinksCampaignTasksJob;
use App\Jobs\BulkUpdateHiddenLinksJob;
use App\Jobs\PublishHiddenLinksJob;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\HiddenLinksCampaignDomains;
use App\Models\Admin\HiddenLinksCampaignLinks;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Services\LiveTaskDomainReplacement\LiveTaskBulkDomainReplacementService;
use App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile;
use App\Services\LocalClientBillingService;
use App\Services\PurgeLocalCampaignDataService;
use App\Support\CampaignTaskStatusFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelWriter;

class HiddenLinkCampaignController extends Controller
{
    use AppliesCampaignListStatusFilter;
    use AppliesSuperAdminCampaignOwnerFilter;
    use AuthorizesAdminCampaign;
    use ProvidesLocalClientsForForms;
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
            'status' => $this->campaignListStatusValidationRule(),
        ]);

        // ✅ Remove empty search from URL
        if ($request->has('search') && trim($request->search) === '') {
            return redirect()->to(
                url()->current().'?'.http_build_query(
                    $request->except('search')
                )
            );
        }

        $limit = config('campaign.pagination.default_limit');

        $query = HiddenLinksCampaign::query()
            ->with('domainCategory')
            ->withCount([
                'links',
                'domains',
                // optional future use
                'tasks as total_tasks',
                'tasks as success_tasks' => fn ($q) => $q->where('status', 'success'),
                'tasks as failed_tasks' => fn ($q) => $q->where('status', 'failed'),
            ]);

        // 🔍 Search by campaign_no
        if ($request->filled('search')) {
            $query->where(
                'campaign_no',
                'LIKE',
                '%'.trim($request->search).'%'
            );
        }

        $this->applyCampaignListStatusFilter($query, $request);

        $admin = Auth::guard('admin')->user();
        $ownerData = $this->scopeCampaignQueryForOwner($query, $request, $admin);

        $campaigns = $query
            ->orderByDesc('id')
            ->paginate($limit)
            ->appends($request->all());

        $offset = ($campaigns->currentPage() - 1) * $limit;

        $replaceableCampaignIds = [];
        if ($admin->canCreateCampaigns()) {
            $replaceableCampaignIds = app(LiveTaskBulkDomainReplacementService::class)
                ->replaceableCampaignIds(
                    LiveTaskReplacementProfile::hiddenLinks(),
                    $campaigns->pluck('id')->all(),
                );
        }

        return view(
            'admin.campaigns.pbn-hidden-links.hidden-links-campaign',
            array_merge(compact('campaigns', 'offset', 'replaceableCampaignIds'), $ownerData)
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

        $campaignId = 'HLC-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        // ** now providing user domain sets
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();

        $sites = Domain::all();

        return view('admin.campaigns.pbn-hidden-links.create-hidden-links-campaign', compact('campaignId', 'domainCategory', 'domainSets', 'sites'))
            ->with('localClients', $this->activeLocalClientsForForms());
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
            $base = 'campaign-'.now()->timestamp;
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
            'campaign_no' => 'required|string',
            'domain_category_id' => 'nullable|integer|exists:domain_categories,id',
            'sidebar_quantity' => 'required|integer|min:1',

            'keywordsDataHolder' => 'required|string', // JSON
            'sel_domains' => 'required|integer|in:0,1,2',
            'campaigns_domains' => 'required|string', // JSON
            'local_client_id' => 'nullable|integer|exists:local_clients,id',
            'billing_currency' => ['nullable', 'string', Rule::in(\App\Support\CurrencyFormatter::supportedCodes())],
        ]);

        $sidebarCount = (int) $request->sidebar_quantity;

        // =====================================================
        // 2) Decode + validate links
        // =====================================================
        $links = json_decode((string) $request->keywordsDataHolder, true);
        if (! is_array($links)) {
            return back()->with('cus__error', 'Links data is invalid JSON.')->withInput();
        }

        // =====================================================
        // 3) Decode + validate domains
        // =====================================================
        $domainIds = json_decode((string) $request->campaigns_domains, true);
        if (! is_array($domainIds)) {
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
            $url = trim((string) ($row['url'] ?? ''));
            $kw = trim((string) ($row['keyword'] ?? ''));

            if ($url === '' || $kw === '') {
                return back()->with(
                    'cus__error',
                    'Link row #'.($i + 1).' url/keyword cannot be empty.'
                )->withInput();
            }
        }

        $domainMethods = ['random', 'domain_set', 'manual'];

        // updated campaign no

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        // =====================================================
        // 6) Atomic DB transaction
        // =====================================================
        try {
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
                    'campaign_no' => $campaignNo,
                    'domain_category_id' => $request->domain_category_id ?: null,
                    'admin_id' => auth('admin')->id(),

                    'sidebar_count' => $sidebarCount,
                    'domain_method' => $domainMethods[(int) $request->sel_domains],
                    'status' => 'queued',

                    'total_targets' => $sidebarCount,
                    'completed_targets' => 0,
                    'failed_targets' => 0,
                ]);

                // -----------------------------------------
                // B) Insert LINKS (keep IDs by index)
                // -----------------------------------------
                $linkIds = [];

                foreach ($links as $idx => $row) {
                    $link = HiddenLinksCampaignLinks::create([
                        'hidden_links_campaigns_id' => $campaign->id,
                        'sort_order' => $idx + 1,
                        'target_url' => trim($row['url']),
                        'anchor_keyword' => trim($row['keyword']),
                        'nofollow' => ! empty($row['nofollow']),
                        'sponsored' => ! empty($row['sponsored']),
                        'ugc' => ! empty($row['ugc']),
                        'noopener' => ! empty($row['noopener']),
                        'noreferrer' => ! empty($row['noreferrer']),
                        'raw_rel_attr' => trim($row['raw_rel_attr'] ?? ''),
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
                        'domain_id' => $domainId,
                    ]);

                    $domainRowIds[$idx] = $row->id;
                }

                // -----------------------------------------
                // D) Create TASKS (domain[i] ↔ link[i])
                // -----------------------------------------
                $taskIds = [];

                for ($i = 0; $i < $sidebarCount; $i++) {
                    $task = HiddenLinksCampaignTasks::create([
                        'hidden_links_campaigns_id' => $campaign->id,
                        'hidden_links_campaigns_domain_id' => $domainRowIds[$i],
                        'hidden_links_campaigns_link_id' => $linkIds[$i],

                        'status' => 'queued',
                        'attempt_count' => 0,
                        'max_attempts' => 5,

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

                app(LocalClientBillingService::class)->applyFromRequest(
                    $request,
                    $campaign,
                    $domainIds,
                    'hidden_links',
                );

                return $campaign;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

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
            ->with(['domainCategory', 'localClient'])
            ->findOrFail($id);

        $limit = 20;
        $statusFilter = $request->string('status')->toString();
        $tasksQuery = HiddenLinksCampaignTasks::query()->where('hidden_links_campaigns_id', $campaign->id);
        $statusCounts = CampaignTaskStatusFilter::counts($tasksQuery);

        $campaignTasks = CampaignTaskStatusFilter::apply(
            HiddenLinksCampaignTasks::query()
                ->where('hidden_links_campaigns_id', $campaign->id)
                ->with([
                    'domainRow.domain',
                    'linkRow',
                ]),
            $statusFilter
        )
            ->orderBy('id')
            ->paginate($limit)
            ->withQueryString();

        $offset = ($campaignTasks->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-hidden-links.view-campaign',
            compact('campaign', 'campaignTasks', 'offset', 'statusFilter', 'statusCounts')
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

        // 3️⃣ Fetch tasks with lean columns only (avoid loading heavy JSON payloads)
        $tasks = HiddenLinksCampaignTasks::query()
            ->select([
                'id',
                'hidden_links_campaigns_id',
                'hidden_links_campaigns_domain_id',
                'hidden_links_campaigns_link_id',
                'status',
                'created_at',
            ])
            ->with([
                'domainRow:id,hidden_links_campaigns_id,domain_id',
                'domainRow.domain:id,name',
                'linkRow:id,hidden_links_campaigns_id,target_url,anchor_keyword,nofollow',
            ])
            ->where('hidden_links_campaigns_id', $campaign->id)
            ->orderBy('id')
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

        $baseTaskQuery = HiddenLinksCampaignTasks::query()
            ->where('hidden_links_campaigns_id', $campaign->id);

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
       4️⃣ FILL ROWS (chunked to avoid memory exhaustion)
       ========================================================= */
        $sno = 1;
        (clone $baseTaskQuery)
            ->select([
                'id',
                'hidden_links_campaigns_id',
                'hidden_links_campaigns_domain_id',
                'hidden_links_campaigns_link_id',
                'status',
                'created_at',
            ])
            ->with([
                'domainRow:id,hidden_links_campaigns_id,domain_id',
                'domainRow.domain:id,name',
                'linkRow:id,hidden_links_campaigns_id,target_url,anchor_keyword,nofollow',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($tasks) use (&$sno, $hasNofollow, $writer) {
                foreach ($tasks as $task) {
                    $domainName = $task->domainRow?->domain?->name ?? '-';

                    $row = [
                        'S.No' => $sno++,
                        'Live Link' => $domainName,
                        'Domain' => $domainName,
                        'Keyword' => $task->linkRow?->anchor_keyword ?? '-',
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
            });

        return $writer->toBrowser();
    }

    /**
     * Edit campaign: show batches (distinct keyword+url) for bulk update.
     */
    public function edit(string $id)
    {
        $campaign = HiddenLinksCampaign::with(['links', 'domains', 'localClient'])->findOrFail($id);
        $this->authorizeCampaignAccess($campaign);

        $links = HiddenLinksCampaignLinks::where('hidden_links_campaigns_id', $campaign->id)->get();
        $batches = [];
        foreach ($links as $link) {
            $k = trim((string) ($link->anchor_keyword ?? ''));
            $u = trim((string) ($link->target_url ?? ''));
            $key = $k."\n".$u;
            if (! isset($batches[$key])) {
                $batches[$key] = [
                    'representative_link_id' => $link->id,
                    'keyword' => $k,
                    'url' => $u,
                    'link_ids' => [],
                ];
            }
            $batches[$key]['link_ids'][] = $link->id;
        }
        foreach ($batches as &$batch) {
            $batch['count'] = count($batch['link_ids']);
            $batch['on_remote'] = HiddenLinksCampaignTasks::where('hidden_links_campaigns_id', $campaign->id)
                ->whereIn('hidden_links_campaigns_link_id', $batch['link_ids'])
                ->whereNotNull('remote_id')
                ->where('status', 'success')
                ->count();
            unset($batch['link_ids']);
        }
        unset($batch);
        $distinctBatches = array_values($batches);
        $allLinksForBulk = HiddenLinksCampaignLinks::where('hidden_links_campaigns_id', $campaign->id)
            ->orderBy('id')
            ->get(['id', 'anchor_keyword', 'target_url'])
            ->map(fn ($link) => [
                'link_id' => (int) $link->id,
                'keyword' => trim((string) ($link->anchor_keyword ?? '')),
                'url' => trim((string) ($link->target_url ?? '')),
            ])
            ->all();

        return view(
            'admin.campaigns.pbn-hidden-links.edit-campaign',
            array_merge(
                compact('campaign', 'distinctBatches', 'allLinksForBulk'),
                ['localClients' => $this->activeLocalClientsForForms()]
            )
        );
    }

    /**
     * Bulk update: queue job(s), set last_bulk_updated_at and content_updated_at optimistically.
     */
    public function update(Request $request, string $id)
    {
        $campaign = HiddenLinksCampaign::findOrFail($id);
        if ($request->input('edit_kw_tab') === 'campaign') {
            $validated = $request->validate([
                'campaign_no' => 'required|string|max:191',
            ]);
            $raw = trim((string) $validated['campaign_no']);
            if ($raw === '') {
                return back()->with('cus__error', 'Campaign title is required.')
                    ->with('edit_hidden_link_campaign_tab', 'campaign');
            }

            $base = Str::slug($raw);
            if ($base === '') {
                $base = 'campaign-'.now()->timestamp;
            }
            $slug = $base;
            $counter = 1;
            while (
                HiddenLinksCampaign::where('campaign_no', $slug)
                    ->where('id', '!=', $campaign->id)
                    ->exists()
            ) {
                $slug = "{$base}-{$counter}";
                $counter++;
            }

            $campaign->update(['campaign_no' => $slug]);

            return redirect()
                ->route('admin.hidden.link.campaign.edit', $campaign->id)
                ->with('cus__success', 'Campaign title updated.')
                ->with('edit_hidden_link_campaign_tab', 'campaign');
        }

        $representativeLinkIds = $request->input('batch_representative_link_id', []);
        if (! is_array($representativeLinkIds)) {
            $representativeLinkIds = [];
        }
        $isBulkTextarea = $request->exists('bulk_urls') && $request->exists('bulk_keywords');
        if ($isBulkTextarea) {
            $expected = count($representativeLinkIds);
            $batchUrls = $this->parseManualLinesStrict((string) $request->input('bulk_urls', ''), $expected);
            $batchKeywords = $this->parseManualLinesStrict((string) $request->input('bulk_keywords', ''), $expected);
            if ($batchUrls === null || $batchKeywords === null) {
                return redirect()
                    ->route('admin.hidden.link.campaign.edit', $campaign->id)
                    ->with('cus__error', 'Bulk URLs and Bulk Keywords must each have exactly '.$expected.' non-empty lines.')
                    ->withInput($request->only(['bulk_urls', 'bulk_keywords']))
                    ->with(
                        'edit_hidden_link_campaign_tab',
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

        $updates = [];
        $queuedBatches = 0;

        foreach ($representativeLinkIds as $index => $repLinkId) {
            $repLinkId = (int) $repLinkId;
            $representative = HiddenLinksCampaignLinks::where('hidden_links_campaigns_id', $campaign->id)->find($repLinkId);
            if (! $representative) {
                continue;
            }

            $newKeyword = trim((string) ($batchKeywords[$index] ?? ''));
            $newUrl = trim((string) ($batchUrls[$index] ?? ''));

            if ($newKeyword === '' || $newUrl === '') {
                continue;
            }

            $oldKeyword = trim((string) ($representative->anchor_keyword ?? ''));
            $oldUrl = trim((string) ($representative->target_url ?? ''));

            if ($oldKeyword === $newKeyword && $oldUrl === $newUrl) {
                continue;
            }

            $linkIds = $isBulkTextarea
                ? [$representative->id]
                : HiddenLinksCampaignLinks::where('hidden_links_campaigns_id', $campaign->id)
                    ->where('anchor_keyword', $representative->anchor_keyword)
                    ->where('target_url', $representative->target_url)
                    ->pluck('id')
                    ->all();

            $tasks = HiddenLinksCampaignTasks::with(['domainRow.domain', 'linkRow'])
                ->where('hidden_links_campaigns_id', $campaign->id)
                ->whereIn('hidden_links_campaigns_link_id', $linkIds)
                ->whereNotNull('remote_id')
                ->where('status', 'success')
                ->get();

            foreach ($tasks as $task) {
                $domain = $task->domainRow?->domain;
                if (! $domain || ! $domain->api_key) {
                    continue;
                }
                $updates[] = ['task_id' => $task->id, 'keyword' => $newKeyword, 'link' => $newUrl];
            }

            HiddenLinksCampaignLinks::whereIn('id', $linkIds)->update([
                'anchor_keyword' => $newKeyword,
                'target_url' => $newUrl,
            ]);
            $queuedBatches++;
        }

        if ($queuedBatches === 0) {
            return redirect()
                ->route('admin.hidden.link.campaign.edit', $campaign->id)
                ->with('cus__error', 'No changes to apply (keyword and URL required per batch).')
                ->with(
                    'edit_hidden_link_campaign_tab',
                    in_array($request->input('edit_kw_tab'), ['normal', 'bulk'], true)
                        ? $request->input('edit_kw_tab')
                        : 'normal'
                );
        }

        $campaign->update(['last_bulk_updated_at' => now()]);

        if (count($updates) > 0) {
            $taskIdsToMark = array_unique(array_column($updates, 'task_id'));
            HiddenLinksCampaignTasks::whereIn('id', $taskIdsToMark)->update(['content_updated_at' => now()]);
            $batchSize = 20;
            $chunks = array_chunk($updates, $batchSize);
            foreach ($chunks as $chunk) {
                BulkUpdateHiddenLinksJob::dispatch($chunk)->onQueue('update_hidden_links');
            }
            $msg = $queuedBatches.' batch(es) updated in DB. '.count($updates).' link(s) queued for remote update. Run: php artisan queue:work --queue=update_hidden_links';
        } else {
            $msg = $queuedBatches.' batch(es) updated in database (no published links on remote yet).';
        }

        return redirect()
            ->route('admin.hidden.link.campaign.show', $campaign->id)
            ->with('cus__success', $msg);
    }

    /**
     * Retry a failed task: reset to queued and dispatch PublishHiddenLinksJob.
     */
    public function retryTask(string $id)
    {
        $task = HiddenLinksCampaignTasks::with('campaign')->find($id);

        if (! $task || ! $task->campaign) {
            return back()->with('cus__error', 'Task or campaign not found.');
        }

        if ($task->status !== 'failed') {
            return back()->with('cus__error', 'Only failed tasks can be retried.');
        }

        $campaign = $task->campaign;
        if (in_array($campaign->status, ['paused', 'cancelled'], true)) {
            return back()->with('cus__error', 'Cannot retry: campaign is paused or cancelled.');
        }

        DB::transaction(function () use ($task) {
            $fresh = HiddenLinksCampaignTasks::lockForUpdate()->find($task->id);
            if (! $fresh || $fresh->status !== 'failed') {
                return;
            }
            $fresh->status = 'queued';
            $fresh->attempt_count = 0;
            $fresh->last_error = null;
            $fresh->next_retry_at = null;
            $fresh->locked_at = null;
            $fresh->lock_token = null;
            $fresh->finished_at = null;
            $fresh->save();
        });

        PublishHiddenLinksJob::dispatch($task->id)->onQueue('hidden_links_campaigns');

        return back()->with('cus__success', 'Task queued for retry.');
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

    /**
     * Single delete: remote delete then decrement campaign and delete task/link/domain.
     * If this was the last task, the campaign is deleted. Uses transaction + lock for correct counts.
     */
    public function deleteTask(string $id)
    {
        $task = HiddenLinksCampaignTasks::with(['domainRow.domain', 'linkRow', 'campaign'])->find($id);

        if (! $task || ! $task->campaign) {
            return back()->with('cus__error', 'Task or campaign not found.');
        }

        $postStatus = $task->status;
        $campaignId = $task->campaign->id;
        $campaignDeleted = false;

        if ($task->remote_id) {
            $domain = $task->domainRow?->domain;
            if ($domain && $domain->api_key) {
                $res = HiddenLinksApiService::deleteEntry($domain->name, $domain->api_key, $task->remote_id);
                if (! $res->successful()) {
                    return back()->with('cus__error', 'Remote delete failed: '.$res->body());
                }
            }
        }

        DB::transaction(function () use ($task, $postStatus, $campaignId, &$campaignDeleted) {
            $campaign = HiddenLinksCampaign::lockForUpdate()->find($campaignId);
            if (! $campaign) {
                throw new \RuntimeException('Campaign not found');
            }

            $isLastTask = $campaign->total_targets === 1;

            if ($campaign->total_targets > 0) {
                $campaign->decrement('total_targets');
            }
            if ($postStatus === 'success' && $campaign->completed_targets > 0) {
                $campaign->decrement('completed_targets');
            } elseif ($postStatus === 'failed' && $campaign->failed_targets > 0) {
                $campaign->decrement('failed_targets');
            }

            $linkId = $task->hidden_links_campaigns_link_id;
            $domainRowId = $task->hidden_links_campaigns_domain_id;
            $task->delete();
            if ($linkId) {
                HiddenLinksCampaignLinks::where('id', $linkId)->delete();
            }
            if ($domainRowId) {
                HiddenLinksCampaignDomains::where('id', $domainRowId)->delete();
            }

            if ($isLastTask) {
                $campaign->delete();
                $campaignDeleted = true;
            }
        });

        if ($campaignDeleted) {
            return redirect()->route('admin.hidden.link.campaign.index')->with(
                'cus__success',
                'Hidden link removed. The campaign had no tasks left and was also removed.'
            );
        }

        return back()->with('cus__success', 'Hidden link removed from remote and database.');
    }

    /**
     * Bulk delete: dispatch job to process in background.
     */
    public function bulkDeleteTasks(Request $request, string $id)
    {
        $campaign = HiddenLinksCampaign::findOrFail($id);

        $taskIds = $request->input('task_ids', []);
        if (! is_array($taskIds)) {
            $taskIds = [];
        }
        $taskIds = array_values(array_filter(array_map('intval', $taskIds)));

        if (count($taskIds) === 0) {
            return back()->with('cus__error', 'No tasks selected.');
        }

        BulkDeleteHiddenLinksJob::dispatch($campaign->id, $taskIds)->onQueue('delete_hidden_links');

        return back()->with('cus__success', 'Bulk delete queued. Run: php artisan queue:work --queue=delete_hidden_links');
    }

    /**
     * Bulk delete campaigns from list page: remove from remote (where remote_id) then delete campaigns and related data.
     */
    public function bulkDeleteCampaigns(Request $request)
    {
        $request->validate([
            'campaign_ids' => 'required|array',
            'campaign_ids.*' => 'integer|min:1',
        ]);

        $ids = array_values(array_unique(array_filter($request->input('campaign_ids', []))));
        if (empty($ids)) {
            return redirect()->route('admin.hidden.link.campaign.index')->with('cus__error', 'No campaigns selected.');
        }

        $admin = Auth::guard('admin')->user();
        $query = HiddenLinksCampaign::whereIn('id', $ids);
        if (! $admin->isSuperAdmin()) {
            $query->where('admin_id', $admin->id);
        }
        $found = $query->pluck('id')->all();
        if (empty($found)) {
            return redirect()->route('admin.hidden.link.campaign.index')->with('cus__error', 'No campaigns found or you do not have permission to delete them.');
        }

        BulkDeleteHiddenLinkCampaignsJob::dispatch($found)->onQueue('delete_hidden_links_campaign');

        $msg = count($found).' campaign(s) queued for deletion (remote links will be removed, then data deleted). Run: php artisan queue:work --queue=delete_hidden_links_campaign';

        return redirect()->route('admin.hidden.link.campaign.index')->with('cus__success', $msg);
    }

    /**
     * Remove selected campaigns from the database only. Does not call remote APIs (distinct from {@see bulkDeleteCampaigns}).
     */
    public function bulkPurgeLocalCampaigns(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return redirect()->route('admin.hidden.link.campaign.index')->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, HiddenLinksCampaign::class);
        if ($allowed === []) {
            return redirect()->route('admin.hidden.link.campaign.index')->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        foreach ($allowed as $id) {
            PurgeLocalCampaignDataService::purgeHiddenLinksCampaign($id);
        }

        $n = count($allowed);

        return redirect()
            ->route('admin.hidden.link.campaign.index')
            ->with('cus__success', $n.' campaign(s) removed from this dashboard only. Remote hidden links were not deleted.');
    }

    /**
     * Bulk retry all failed tasks across selected hidden links campaigns.
     */
    public function bulkRetryFailed(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return back()->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, HiddenLinksCampaign::class);
        if ($allowed === []) {
            return back()->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        BulkRetryHiddenLinksCampaignTasksJob::dispatch($allowed);

        $n = count($allowed);

        return redirect()
            ->route('admin.hidden.link.campaign.index')
            ->with('cus__success', 'Bulk retry queued for '.$n.' hidden links campaign(s). All failed tasks will be retried in the background. Run the queue worker to process them.');
    }

    /**
     * Show form to edit single task (keyword/link). Increased timeout in service (120s).
     */
    public function editTask(string $id)
    {
        $task = HiddenLinksCampaignTasks::with(['domainRow.domain', 'linkRow', 'campaign'])->find($id);

        if (! $task) {
            return back()->with('cus__error', 'Task not found');
        }
        if (! $task->remote_id) {
            return back()->with('cus__error', 'Task has no remote_id; only published entries can be updated.');
        }

        return view('admin.campaigns.pbn-hidden-links.edit-task', compact('task'));
    }

    /**
     * Single update: fetch index by remote_id, POST update, update DB and content_updated_at. Timeout 120s in service.
     */
    public function updateTask(Request $request, string $id)
    {
        $task = HiddenLinksCampaignTasks::with(['domainRow.domain', 'linkRow'])->find($id);

        if (! $task || ! $task->linkRow) {
            return back()->with('cus__error', 'Task or link not found');
        }
        if (! $task->remote_id) {
            return back()->with('cus__error', 'Task has no remote_id; cannot update on remote.');
        }

        $domain = $task->domainRow?->domain;
        if (! $domain || ! $domain->api_key) {
            return back()->with('cus__error', 'Domain or API key missing');
        }

        $request->validate([
            'keyword' => 'required|string|max:500',
            'link' => 'required|url|max:500',
        ]);

        $keyword = trim($request->keyword);
        $link = trim($request->link);

        $res = HiddenLinksApiService::updateEntry(
            $domain->name,
            $domain->api_key,
            $task->remote_id,
            $keyword,
            $link,
            array_values(array_filter([
                ($task->linkRow->nofollow ?? false) ? 'nofollow' : null,
                ($task->linkRow->sponsored ?? false) ? 'sponsored' : null,
            ]))
        );
        if (! $res->successful()) {
            return back()->with('cus__error', 'Remote update failed: '.$res->body());
        }

        $task->linkRow->update([
            'anchor_keyword' => $keyword,
            'target_url' => $link,
        ]);
        $task->update(['content_updated_at' => now()]);

        return back()->with('cus__success', 'Hidden link updated on remote and in database.');
    }

    /**
     * Remove the specified resource from storage (full campaign - not implemented; use bulk delete or single delete).
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Remove campaign data from this application only. Remote hidden links are not deleted.
     */
    public function purgeLocalOnly(string $id)
    {
        $campaign = HiddenLinksCampaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }
        $this->authorizeCampaignAccess($campaign);
        PurgeLocalCampaignDataService::purgeHiddenLinksCampaign((int) $campaign->id);

        return redirect()
            ->route('admin.hidden.link.campaign.index')
            ->with(
                'cus__success',
                'Campaign removed from this dashboard only. Remote hidden links were not deleted.'
            );
    }
}
