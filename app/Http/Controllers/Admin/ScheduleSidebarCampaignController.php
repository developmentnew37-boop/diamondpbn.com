<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AppliesCampaignListStatusFilter;
use App\Http\Controllers\Admin\Concerns\AppliesSuperAdminCampaignOwnerFilter;
use App\Http\Controllers\Admin\Concerns\AuthorizesAdminCampaign;
use App\Http\Controllers\Admin\Concerns\ProvidesLocalClientsForForms;
use App\Http\Controllers\Admin\Concerns\ValidatesBulkCampaignIds;
use App\Http\Controllers\Controller;
use App\Jobs\BulkRetryScheduleSidebarCampaignTasksJob;
use App\Jobs\BulkUpdateScheduleSidebarBlogrollJob;
use App\Jobs\DeleteScheduleSidebarCampaignJob;
use App\Jobs\PublishScheduledSidebarBlogrollJob;
use App\Jobs\SyncConvertedSidebarCampaignRemoteStatusJob;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignDate;
use App\Models\Admin\ScheduleSidebarCampaignDomain;
use App\Models\Admin\ScheduleSidebarCampaignLink;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Services\BlogrollApiService;
use App\Services\ConvertedSidebarRemoteSyncService;
use App\Services\LiveTaskDomainReplacement\LiveTaskBulkDomainReplacementService;
use App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile;
use App\Services\LocalClientBillingService;
use App\Services\PurgeLocalCampaignDataService;
use App\Services\ScheduleSidebarCampaignTargetCounterService;
use App\Services\SidebarLiveToDripfeedConversionService;
use App\Support\CampaignTaskStatusFilter;
use App\Support\ReportDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ScheduleSidebarCampaignController extends Controller
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
        /* ============================
     | Validate URL inputs only
     ============================ */
        $request->validate([
            'search' => 'nullable|string|max:150',
            'filter_user' => 'nullable|string|max:20',
            'status' => $this->campaignListStatusValidationRule(),
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        /* ============================
     | Remove empty search from URL
     ============================ */
        if ($request->has('search') && trim($request->search) === '') {
            return redirect()->to(
                url()->current().'?'.http_build_query(
                    $request->except('search')
                )
            );
        }

        $limit = config('campaign.pagination.default_limit');

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
                '%'.trim($request->search).'%'
            );
        }

        /* ============================
     | Filter: status
     ============================ */
        $this->applyCampaignListStatusFilter($query, $request);

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
        $ownerData = $this->scopeCampaignQueryForOwner($query, $request, $admin);

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

        $replaceableCampaignIds = [];
        if ($admin->canCreateCampaigns()) {
            $replaceableCampaignIds = app(LiveTaskBulkDomainReplacementService::class)
                ->replaceableCampaignIds(
                    LiveTaskReplacementProfile::scheduleSidebar(),
                    $campaigns->pluck('id')->all(),
                );
        }

        return view(
            'admin.campaigns.pbn-sidebar.schedule-sidebar-campaign',
            array_merge(compact('campaigns', 'offset', 'replaceableCampaignIds'), $ownerData)
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        // return view('admin.cam')

        $campaignId = 'SCH-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        // ** now providing user domain sets
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();

        $sites = Domain::all();

        return view('admin.campaigns.pbn-sidebar.create-schedule-sidebar-campaign', compact('campaignId', 'domainCategory', 'domainSets', 'sites'))
            ->with('localClients', $this->activeLocalClientsForForms());
    }

    /**
     * Store a newly created resource in storage.
     */
    private function generateUniqueCampaignNo(string $input, ?int $excludeId = null): string
    {
        $base = Str::slug($input);
        if ($base === '') {
            $base = 'campaign-'.now()->timestamp;
        }
        $slug = $base;
        $counter = 1;
        do {
            $query = ScheduleSidebarCampaign::where('campaign_no', $slug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
            if (! $query->exists()) {
                break;
            }
            $slug = "{$base}-{$counter}";
            $counter++;
        } while (true);

        return $slug;
    }

    public function store(Request $request)
    {
        $dateQuantitiesRaw = $request->input('date_quantities');
        $useDateTable = ! empty($dateQuantitiesRaw);

        if ($useDateTable) {
            $dateRows = is_string($dateQuantitiesRaw) ? json_decode($dateQuantitiesRaw, true) : $dateQuantitiesRaw;
            if (! is_array($dateRows) || count($dateRows) === 0) {
                return back()->with('cus__error', 'Invalid or empty date distribution. Generate the date table and set quantities.')->withInput();
            }
            $qty = 0;
            $dateMin = null;
            $dateMax = null;
            foreach ($dateRows as $row) {
                $qu = (int) ($row['quantity'] ?? 0);
                if ($qu > 0) {
                    $d = $row['date'] ?? null;
                    if ($d) {
                        $qty += $qu;
                        $parsed = Carbon::parse($d);
                        if ($dateMin === null || $parsed->lt($dateMin)) {
                            $dateMin = $parsed;
                        }
                        if ($dateMax === null || $parsed->gt($dateMax)) {
                            $dateMax = $parsed;
                        }
                    }
                }
            }
            if ($qty < 1 || $dateMin === null || $dateMax === null) {
                return back()->with('cus__error', 'Date distribution must have at least one date with quantity > 0.')->withInput();
            }
            $scheduleFrom = $dateMin->format('Y-m-d');
            $scheduleTo = $dateMax->format('Y-m-d');
        } else {
            $request->validate([
                'sidebar_quantity' => 'required|integer|min:1',
                'schedule_from_date' => 'required|date',
                'schedule_to_date' => 'required|date|after_or_equal:schedule_from_date',
            ]);
            $qty = (int) $request->sidebar_quantity;
            $scheduleFrom = $request->schedule_from_date;
            $scheduleTo = $request->schedule_to_date;
        }

        $request->validate([
            'campaign_no' => 'required|string',
            'domain_category_id' => 'nullable|integer|exists:domain_categories,id',
            'keywordsDataHolder' => 'required|string',
            'campaigns_domains' => 'required|string',
            'local_client_id' => 'nullable|integer|exists:local_clients,id',
            'billing_currency' => ['nullable', 'string', Rule::in(\App\Support\CurrencyFormatter::supportedCodes())],
        ]);

        $links = json_decode($request->keywordsDataHolder, true);
        if (! is_array($links)) {
            return back()->with('cus__error', 'Invalid links JSON')->withInput();
        }
        $links = array_values($links);

        $domainIds = json_decode($request->campaigns_domains, true);
        if (! is_array($domainIds)) {
            return back()->with('cus__error', 'Invalid domains JSON')->withInput();
        }
        $domainIds = array_values(array_map('intval', $domainIds));

        if (count($links) !== $qty || count($domainIds) !== $qty) {
            return back()
                ->with('cus__error', 'Links and domains must match sidebar quantity ('.$qty.').')
                ->withInput();
        }

        // ✅ Validate each link row has keyword + url (handle both single and arrays)
        foreach ($links as $i => $row) {
            $url = $row['url'] ?? null;
            $kw = $row['keyword'] ?? null;

            // Handle both single strings and arrays
            if (is_array($url)) {
                $url = ! empty($url) ? $url : null;
            } else {
                $url = trim((string) $url);
                $url = $url !== '' ? $url : null;
            }

            if (is_array($kw)) {
                $kw = ! empty($kw) ? $kw : null;
            } else {
                $kw = trim((string) $kw);
                $kw = $kw !== '' ? $kw : null;
            }

            if ($url === null || $kw === null) {
                return back()->with(
                    'cus__error',
                    'Link row #'.($i + 1).' url/keyword cannot be empty.'
                )->withInput();
            }
        }

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        try {
            if ($useDateTable) {
                $this->storeWithDateTable($request, $campaignNo, $qty, $scheduleFrom, $scheduleTo, $links, $domainIds, $dateRows);
            } else {
                $from = Carbon::parse($scheduleFrom)->startOfDay();
                $to = Carbon::parse($scheduleTo)->startOfDay();
                $totalDays = $from->diffInDays($to) + 1;
                $perDay = intdiv($qty, $totalDays);
                $remainder = $qty % $totalDays;
                $this->storeWithDateRange($request, $campaignNo, $qty, $links, $domainIds, $from, $totalDays, $perDay, $remainder);
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('cus__error', 'Could not create scheduled sidebar campaign: '.$e->getMessage())->withInput();
        }

        return redirect()
            ->route('admin.schedule.sidebar.campaign.create')
            ->with('cus__success', 'Scheduled sidebar campaign created successfully.');
    }

    /**
     * Store campaign with per-date quantity table (date distribution).
     */
    private function storeWithDateTable(
        Request $request,
        string $campaignNo,
        int $qty,
        string $scheduleFrom,
        string $scheduleTo,
        array $links,
        array $domainIds,
        array $dateRows
    ): void {
        DB::transaction(function () use ($request, $campaignNo, $qty, $scheduleFrom, $scheduleTo, $links, $domainIds, $dateRows) {
            $schedule = ScheduleSidebarCampaign::create([
                'campaign_no' => $campaignNo,
                'admin_id' => auth('admin')->id(),
                'domain_category_id' => $request->domain_category_id,
                'schedule_from_date' => $scheduleFrom,
                'schedule_to_date' => $scheduleTo,
                'status' => 'queued',
                'total_targets' => $qty,
                'completed_targets' => 0,
                'failed_targets' => 0,
            ]);

            foreach ($domainIds as $i => $domainId) {
                ScheduleSidebarCampaignDomain::create([
                    'schedule_sidebar_campaign_id' => $schedule->id,
                    'domain_id' => $domainId,
                ]);
            }
            $domainMap = ScheduleSidebarCampaignDomain::where('schedule_sidebar_campaign_id', $schedule->id)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $method = (string) ($request->keywordmethod ?? 'normal');
            // Only Raw Anchor uses JSON arrays for sidebar campaigns
            $isMultiple = ($method === 'rawanchor');

            foreach ($links as $i => $row) {
                $kwVal = $row['keyword'] ?? null;
                $urlVal = $row['url'] ?? null;

                $kwType = $isMultiple ? 'json' : 'single';
                $urlType = $isMultiple ? 'json' : 'single';

                if ($isMultiple) {
                    $kwArr = is_array($kwVal) ? $kwVal : (is_null($kwVal) ? [] : [$kwVal]);
                    $urlArr = is_array($urlVal) ? $urlVal : (is_null($urlVal) ? [] : [$urlVal]);

                    ScheduleSidebarCampaignLink::create([
                        'schedule_sidebar_campaign_id' => $schedule->id,
                        'target_url' => json_encode($urlArr),
                        'anchor_keyword' => json_encode($kwArr),
                        'target_url_type' => $urlType,
                        'anchor_keyword_type' => $kwType,
                        'nofollow' => ! empty($row['nofollow']),
                        'sponsored' => ! empty($row['sponsored']),
                        'ugc' => ! empty($row['ugc']),
                        'noopener' => ! empty($row['noopener']),
                        'noreferrer' => ! empty($row['noreferrer']),
                        'raw_rel_attr' => trim((string) ($row['raw_rel_attr'] ?? '')),
                        'sort_order' => $i + 1,
                    ]);
                } else {
                    ScheduleSidebarCampaignLink::create([
                        'schedule_sidebar_campaign_id' => $schedule->id,
                        'target_url' => trim((string) $urlVal),
                        'anchor_keyword' => trim((string) $kwVal),
                        'target_url_type' => $urlType,
                        'anchor_keyword_type' => $kwType,
                        'nofollow' => ! empty($row['nofollow']),
                        'sponsored' => ! empty($row['sponsored']),
                        'ugc' => ! empty($row['ugc']),
                        'noopener' => ! empty($row['noopener']),
                        'noreferrer' => ! empty($row['noreferrer']),
                        'raw_rel_attr' => trim((string) ($row['raw_rel_attr'] ?? '')),
                        'sort_order' => $i + 1,
                    ]);
                }
            }
            $linkMap = ScheduleSidebarCampaignLink::where('schedule_sidebar_campaign_id', $schedule->id)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $dateRowsFiltered = [];
            foreach ($dateRows as $row) {
                $qu = (int) ($row['quantity'] ?? 0);
                $d = $row['date'] ?? null;
                if ($qu > 0 && $d) {
                    $dateRowsFiltered[] = [
                        'schedule_sidebar_campaign_id' => $schedule->id,
                        'schedule_date' => $d,
                        'quantity' => $qu,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (! empty($dateRowsFiltered)) {
                ScheduleSidebarCampaignDate::insert($dateRowsFiltered);
            }

            $globalIndex = 0;
            $postRows = [];
            $now = now();
            $dateRowsOrdered = ScheduleSidebarCampaignDate::where('schedule_sidebar_campaign_id', $schedule->id)
                ->orderBy('schedule_date')
                ->get();

            foreach ($dateRowsOrdered as $dateRow) {
                $scheduleAt = Carbon::parse($dateRow->schedule_date)->startOfDay();
                for ($k = 0; $k < $dateRow->quantity; $k++) {
                    if ($globalIndex >= $qty) {
                        break;
                    }
                    $postRows[] = [
                        'schedule_sidebar_campaign_id' => $schedule->id,
                        'schedule_sidebar_campaign_domain_id' => $domainMap[$globalIndex],
                        'schedule_sidebar_campaign_link_id' => $linkMap[$globalIndex],
                        'schedule_sidebar_campaign_date_id' => $dateRow->id,
                        'schedule_at' => $scheduleAt,
                        'status' => 'queued',
                        'attempt_count' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $globalIndex++;
                }
            }

            foreach (array_chunk($postRows, 200) as $chunk) {
                ScheduleSidebarCampaignTask::insert($chunk);
            }

            app(LocalClientBillingService::class)->applyFromRequest(
                $request,
                $schedule,
                $domainIds,
                'schedule_sidebar',
            );
        });
    }

    /**
     * Store campaign with date range and even distribution (legacy).
     */
    private function storeWithDateRange(
        Request $request,
        string $campaignNo,
        int $qty,
        array $links,
        array $domainIds,
        $from,
        int $totalDays,
        int $perDay,
        int $remainder
    ): void {
        DB::transaction(function () use ($request, $campaignNo, $qty, $links, $domainIds, $from, $perDay, $remainder) {
            $schedule = ScheduleSidebarCampaign::create([
                'campaign_no' => $campaignNo,
                'admin_id' => auth('admin')->id(),
                'domain_category_id' => $request->domain_category_id,
                'schedule_from_date' => $request->schedule_from_date,
                'schedule_to_date' => $request->schedule_to_date,
                'status' => 'queued',
                'total_targets' => $qty,
                'completed_targets' => 0,
                'failed_targets' => 0,
            ]);

            $domainMap = [];
            foreach ($domainIds as $i => $domainId) {
                $row = ScheduleSidebarCampaignDomain::create([
                    'schedule_sidebar_campaign_id' => $schedule->id,
                    'domain_id' => $domainId,
                ]);
                $domainMap[$i] = $row->id;
            }

            $linkMap = [];
            $method = (string) ($request->keywordmethod ?? 'normal');
            // Only Raw Anchor uses JSON arrays for sidebar campaigns
            $isMultiple = ($method === 'rawanchor');

            foreach ($links as $i => $row) {
                $kwVal = $row['keyword'] ?? null;
                $urlVal = $row['url'] ?? null;

                $kwType = $isMultiple ? 'json' : 'single';
                $urlType = $isMultiple ? 'json' : 'single';

                if ($isMultiple) {
                    $kwArr = is_array($kwVal) ? $kwVal : (is_null($kwVal) ? [] : [$kwVal]);
                    $urlArr = is_array($urlVal) ? $urlVal : (is_null($urlVal) ? [] : [$urlVal]);

                    $link = ScheduleSidebarCampaignLink::create([
                        'schedule_sidebar_campaign_id' => $schedule->id,
                        'target_url' => json_encode($urlArr),
                        'anchor_keyword' => json_encode($kwArr),
                        'target_url_type' => $urlType,
                        'anchor_keyword_type' => $kwType,
                        'nofollow' => ! empty($row['nofollow']),
                        'sponsored' => ! empty($row['sponsored']),
                        'ugc' => ! empty($row['ugc']),
                        'noopener' => ! empty($row['noopener']),
                        'noreferrer' => ! empty($row['noreferrer']),
                        'raw_rel_attr' => trim((string) ($row['raw_rel_attr'] ?? '')),
                        'sort_order' => $i + 1,
                    ]);
                } else {
                    $link = ScheduleSidebarCampaignLink::create([
                        'schedule_sidebar_campaign_id' => $schedule->id,
                        'target_url' => trim((string) $urlVal),
                        'anchor_keyword' => trim((string) $kwVal),
                        'target_url_type' => $urlType,
                        'anchor_keyword_type' => $kwType,
                        'nofollow' => ! empty($row['nofollow']),
                        'sponsored' => ! empty($row['sponsored']),
                        'ugc' => ! empty($row['ugc']),
                        'noopener' => ! empty($row['noopener']),
                        'noreferrer' => ! empty($row['noreferrer']),
                        'raw_rel_attr' => trim((string) ($row['raw_rel_attr'] ?? '')),
                        'sort_order' => $i + 1,
                    ]);
                }
                $linkMap[$i] = $link->id;
            }

            $rows = [];
            $now = now();
            for ($i = 0; $i < $qty; $i++) {
                if ($i < ($perDay + 1) * $remainder) {
                    $dayIndex = intdiv($i, $perDay + 1);
                } else {
                    $dayIndex = $remainder + intdiv($i - ($perDay + 1) * $remainder, $perDay);
                }
                $scheduleAt = $from->copy()->addDays($dayIndex)->setTime(0, 0);
                $rows[] = [
                    'schedule_sidebar_campaign_id' => $schedule->id,
                    'schedule_sidebar_campaign_domain_id' => $domainMap[$i],
                    'schedule_sidebar_campaign_link_id' => $linkMap[$i],
                    'schedule_at' => $scheduleAt,
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($rows) === 200) {
                    ScheduleSidebarCampaignTask::insert($rows);
                    $rows = [];
                }
            }
            if (! empty($rows)) {
                ScheduleSidebarCampaignTask::insert($rows);
            }

            app(LocalClientBillingService::class)->applyFromRequest(
                $request,
                $schedule,
                $domainIds,
                'schedule_sidebar',
            );
        });
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
            'scheduleDate',
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
            'domain.domain',
            'link',
            'scheduleDate',
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
                'S.No' => $sno++,
                'Domain' => optional($task->domain?->domain)->name ?? '-',
                'Keyword' => ReportDisplay::plain($task->link?->anchor_keyword),
                'URL' => ReportDisplay::plain($task->link?->target_url),
                'Scheduled At' => ($task->scheduleDate?->schedule_date ?? $task->schedule_at)?->format('d M Y H:i') ?? '-',
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
    public function show(Request $request, string $id, ConvertedSidebarRemoteSyncService $remoteSync, SidebarLiveToDripfeedConversionService $conversionService)
    {
        $campaign = ScheduleSidebarCampaign::with([
            'domains.domain',
            'links',
            'sourceSidebarCampaign',
            'localClient',
        ])->findOrFail($id);

        if (filled($campaign->converted_from_sidebar_campaign_id)) {
            if ($conversionService->realignConvertedTaskSlots($campaign) > 0) {
                $campaign->refresh();
            }

            $remoteSync->syncCampaignTasks($campaign);
            $campaign->refresh();

            $stillNeedsSync = ScheduleSidebarCampaignTask::query()
                ->with(['domain.domain'])
                ->where('schedule_sidebar_campaign_id', $campaign->id)
                ->where('is_converted_live', true)
                ->get()
                ->contains(fn (ScheduleSidebarCampaignTask $task) => $remoteSync->needsRemoteSync($task));

            if ($stillNeedsSync) {
                SyncConvertedSidebarCampaignRemoteStatusJob::dispatch((int) $campaign->id)
                    ->onQueue(SyncConvertedSidebarCampaignRemoteStatusJob::QUEUE);
            }
        }

        $limit = 100;
        $statusFilter = $request->string('status')->toString();
        $tasksQuery = ScheduleSidebarCampaignTask::query()->where('schedule_sidebar_campaign_id', $campaign->id);
        $isConvertedLiveCampaign = filled($campaign->converted_from_sidebar_campaign_id);

        if ($isConvertedLiveCampaign) {
            $statusCounts = CampaignTaskStatusFilter::countsForConvertedLivePosts($tasksQuery);
            $campaignTasks = CampaignTaskStatusFilter::applyForConvertedLivePost(
                ScheduleSidebarCampaignTask::query()
                    ->with([
                        'domain.domain',
                        'link',
                        'scheduleDate',
                    ])
                    ->where('schedule_sidebar_campaign_id', $campaign->id),
                $statusFilter !== '' ? $statusFilter : null,
            )
                ->orderByRaw('COALESCE(original_schedule_at, schedule_at) asc')
                ->orderBy('id')
                ->paginate($limit)
                ->withQueryString();
        } else {
            $statusCounts = CampaignTaskStatusFilter::counts($tasksQuery);
            $campaignTasks = CampaignTaskStatusFilter::apply(
                ScheduleSidebarCampaignTask::query()
                    ->with([
                        'domain.domain',
                        'link',
                    ])
                    ->where('schedule_sidebar_campaign_id', $campaign->id),
                $statusFilter
            )
                ->orderByRaw('COALESCE(original_schedule_at, schedule_at) asc')
                ->orderBy('id')
                ->paginate($limit)
                ->withQueryString();
        }

        $offset = ($campaignTasks->currentPage() - 1) * $limit;

        $recentReplacements = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('schedule_sidebar_campaign_domain_replacements')) {
            $recentReplacements = \App\Models\Admin\ScheduleSidebarCampaignDomainReplacement::query()
                ->where('schedule_sidebar_campaign_id', $campaign->id)
                ->whereIn('state', ['dispatch_pending', 'dispatching', 'dispatch_failed', 'completed'])
                ->latest()
                ->limit(10)
                ->get();
        }

        return view(
            'admin.campaigns.pbn-sidebar.view-schedule-campaign',
            compact('campaign', 'campaignTasks', 'offset', 'statusFilter', 'statusCounts', 'isConvertedLiveCampaign', 'recentReplacements')
        );
    }

    /**
     * Edit campaign: campaign no + bulk edit blogroll link batches.
     */
    public function edit(string $id)
    {
        $campaign = ScheduleSidebarCampaign::findOrFail($id);
        $this->authorizeCampaignAccess($campaign);

        $links = ScheduleSidebarCampaignLink::where('schedule_sidebar_campaign_id', $campaign->id)->get();

        $batches = [];
        foreach ($links as $link) {
            // ✅ Handle both single and JSON array types
            $kwType = $link->anchor_keyword_type ?? 'single';
            $urlType = $link->target_url_type ?? 'single';

            if ($kwType === 'json') {
                $keywords = json_decode($link->anchor_keyword, true);
                $keywords = is_array($keywords) ? $keywords : [$link->anchor_keyword];
            } else {
                $keywords = [$link->anchor_keyword];
            }

            if ($urlType === 'json') {
                $urls = json_decode($link->target_url, true);
                $urls = is_array($urls) ? $urls : [$link->target_url];
            } else {
                $urls = [$link->target_url];
            }

            // ✅ Create display string for grouping (show all pairs)
            $displayPairs = [];
            $pairCount = max(count($keywords), count($urls));
            for ($i = 0; $i < $pairCount; $i++) {
                $kw = trim((string) ($keywords[$i] ?? $keywords[0] ?? ''));
                $url = trim((string) ($urls[$i] ?? $urls[0] ?? ''));
                $displayPairs[] = $kw.' → '.$url;
            }
            $key = implode(' | ', $displayPairs);

            if (! isset($batches[$key])) {
                $batches[$key] = [
                    'representative_link_id' => $link->id,
                    'keyword' => $keywords, // ✅ Store as array
                    'url' => $urls,     // ✅ Store as array
                    'is_multiple' => $pairCount > 1,
                    'pair_count' => $pairCount,
                    'link_ids' => [],
                ];
            }
            $batches[$key]['link_ids'][] = $link->id;
        }

        foreach ($batches as &$batch) {
            $batch['count'] = ScheduleSidebarCampaignTask::where('schedule_sidebar_campaign_id', $campaign->id)
                ->whereIn('schedule_sidebar_campaign_link_id', $batch['link_ids'])
                ->whereNotNull('remote_id')
                ->where('status', 'success')
                ->count();
            unset($batch['link_ids']);
        }
        unset($batch);

        $distinctBatches = array_values(array_filter($batches, fn ($b) => $b['count'] > 0));
        $allLinksForBulk = ScheduleSidebarCampaignLink::where('schedule_sidebar_campaign_id', $campaign->id)
            ->orderBy('id')
            ->get(['id', 'anchor_keyword', 'target_url', 'anchor_keyword_type', 'target_url_type'])
            ->map(function ($link) {
                // ✅ Decode JSON arrays for bulk edit display
                $kwType = $link->anchor_keyword_type ?? 'single';
                $urlType = $link->target_url_type ?? 'single';

                if ($kwType === 'json') {
                    $keywords = json_decode($link->anchor_keyword, true);
                    $keywords = is_array($keywords) ? $keywords : [$link->anchor_keyword];
                } else {
                    $keywords = [$link->anchor_keyword];
                }

                if ($urlType === 'json') {
                    $urls = json_decode($link->target_url, true);
                    $urls = is_array($urls) ? $urls : [$link->target_url];
                } else {
                    $urls = [$link->target_url];
                }

                return [
                    'link_id' => (int) $link->id,
                    'keyword' => implode("\n", array_map('trim', $keywords)),
                    'url' => implode("\n", array_map('trim', $urls)),
                ];
            })
            ->all();

        return view(
            'admin.campaigns.pbn-sidebar.edit-schedule-sidebar-campaign',
            compact('campaign', 'distinctBatches', 'allLinksForBulk')
        );
    }

    /**
     * Update campaign no only.
     */
    public function update(Request $request, string $id)
    {
        $campaign = ScheduleSidebarCampaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        $validated = $request->validate(['campaign_no' => 'required|string|max:191']);

        $campaignNo = $this->generateUniqueCampaignNo($validated['campaign_no'], (int) $campaign->id);
        $campaign->update(['campaign_no' => $campaignNo]);

        return back()
            ->with('cus__success', 'Campaign updated.')
            ->with('edit_schedule_sidebar_campaign_tab', 'campaign');
    }

    /**
     * Bulk update blogroll link batches: update DB links, queue remote updates.
     */
    public function bulkUpdate(Request $request, string $id)
    {
        $campaign = ScheduleSidebarCampaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        $representativeLinkIds = $request->input('batch_representative_link_id', []);
        if (! is_array($representativeLinkIds)) {
            $representativeLinkIds = [];
        }

        $editTab = $request->input('edit_kw_tab', 'normal');
        $isBulkTextarea = $request->exists('bulk_urls') && $request->exists('bulk_keywords');
        $isSingleTab = ! $isBulkTextarea && $editTab === 'normal';
        $isRawAnchor = $editTab === 'rawanchor';

        if ($isBulkTextarea) {
            $expected = count($representativeLinkIds);
            $batchUrls = $this->parseManualLinesStrict((string) $request->input('bulk_urls', ''), $expected);
            $batchKeywords = $this->parseManualLinesStrict((string) $request->input('bulk_keywords', ''), $expected);
            if ($batchUrls === null || $batchKeywords === null) {
                return redirect()
                    ->route('admin.schedule.sidebar.campaign.edit', $campaign->id)
                    ->with('cus__error', 'Bulk URLs and Bulk Keywords must each have exactly '.$expected.' non-empty lines.')
                    ->withInput($request->only(['bulk_urls', 'bulk_keywords']))
                    ->with(
                        'edit_schedule_sidebar_campaign_tab',
                        in_array($editTab, ['normal', 'bulk', 'rawanchor'], true)
                            ? $editTab
                            : 'bulk'
                    );
            }
        } elseif ($isRawAnchor) {
            // Handle Raw HTML Anchors
            $expected = count($representativeLinkIds);
            $rawAnchors = $this->parseManualLinesStrict((string) $request->input('raw_html_anchors', ''), $expected);

            if ($rawAnchors === null) {
                return redirect()
                    ->route('admin.schedule.sidebar.campaign.edit', $campaign->id)
                    ->with('cus__error', 'Raw HTML Anchors must have exactly '.$expected.' non-empty lines.')
                    ->withInput($request->only(['raw_html_anchors']))
                    ->with('edit_schedule_sidebar_campaign_tab', 'rawanchor');
            }

            // Parse anchor tags to extract URLs, keywords, and rel attributes
            $batchUrls = [];
            $batchKeywords = [];
            $batchRelAttrs = [];

            foreach ($rawAnchors as $anchorLine) {
                // Extract first anchor tag from the line
                if (preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', $anchorLine, $matches)) {
                    $batchUrls[] = trim($matches[1]);
                    $batchKeywords[] = trim($matches[2]);

                    // Extract rel attribute if present
                    if (preg_match('/rel=["\']([^"\']+)["\']/i', $anchorLine, $relMatches)) {
                        $batchRelAttrs[] = trim($relMatches[1]);
                    } else {
                        $batchRelAttrs[] = '';
                    }
                } else {
                    $batchUrls[] = '';
                    $batchKeywords[] = '';
                    $batchRelAttrs[] = '';
                }
            }
        } else {
            $batchKeywords = $request->input('batch_keyword', []);
            $batchUrls = $request->input('batch_url', []);
            $batchKeywords = is_array($batchKeywords) ? array_values($batchKeywords) : [];
            $batchUrls = is_array($batchUrls) ? array_values($batchUrls) : [];
            $batchRelAttrs = []; // Not applicable for normal/bulk mode
        }

        $updates = [];
        $updatedBatches = 0;

        foreach ($representativeLinkIds as $index => $repLinkId) {
            $repLinkId = (int) $repLinkId;
            $representative = ScheduleSidebarCampaignLink::where('schedule_sidebar_campaign_id', $campaign->id)->find($repLinkId);
            if (! $representative) {
                continue;
            }

            $newKeyword = trim((string) ($batchKeywords[$index] ?? ''));
            $newUrl = trim((string) ($batchUrls[$index] ?? ''));
            $newRelAttr = $isRawAnchor ? trim((string) ($batchRelAttrs[$index] ?? '')) : '';

            if ($newKeyword === '' || $newUrl === '') {
                continue;
            }

            $oldKeyword = trim((string) ($representative->anchor_keyword ?? ''));
            $oldUrl = trim((string) ($representative->target_url ?? ''));
            $oldRelAttr = trim((string) ($representative->raw_rel_attr ?? ''));

            // Check if anything changed
            if ($oldKeyword === $newKeyword && $oldUrl === $newUrl && $oldRelAttr === $newRelAttr) {
                continue;
            }

            $linkIds = ($isBulkTextarea || $isSingleTab || $isRawAnchor)
                ? [$representative->id]
                : ScheduleSidebarCampaignLink::where('schedule_sidebar_campaign_id', $campaign->id)
                    ->where('anchor_keyword', $representative->anchor_keyword)
                    ->where('target_url', $representative->target_url)
                    ->pluck('id')
                    ->all();

            $updateData = [
                'anchor_keyword' => $newKeyword,
                'target_url' => $newUrl,
            ];

            // Update raw_rel_attr only in raw anchor mode
            if ($isRawAnchor) {
                $updateData['raw_rel_attr'] = $newRelAttr;
            }

            ScheduleSidebarCampaignLink::whereIn('id', $linkIds)->update($updateData);
            $updatedBatches++;

            $taskIds = ScheduleSidebarCampaignTask::where('schedule_sidebar_campaign_id', $campaign->id)
                ->whereIn('schedule_sidebar_campaign_link_id', $linkIds)
                ->where('status', 'success')
                ->whereNotNull('remote_id')
                ->pluck('id')
                ->all();

            foreach ($taskIds as $taskId) {
                $updates[] = [
                    'task_id' => $taskId,
                    'keyword' => $newKeyword,
                    'link' => $newUrl,
                ];
            }
        }

        if (count($updates) > 0) {
            BulkUpdateScheduleSidebarBlogrollJob::dispatch($updates)->onQueue('schedule_sidebar_bulk_updates');
        }

        if ($updatedBatches === 0) {
            $msg = 'No link changes were made.';
        } elseif (count($updates) > 0) {
            $msg = 'Batches updated. '.count($updates).' link(s) queued to update on remote.';
        } else {
            $msg = 'Batches updated. No published links to update on remote.';
        }

        return redirect()
            ->route('admin.schedule.sidebar.campaign.edit', $campaign->id)
            ->with($updatedBatches > 0 ? 'cus__success' : 'cus__error', $msg)
            ->with(
                'edit_schedule_sidebar_campaign_tab',
                in_array($editTab, ['normal', 'bulk', 'rawanchor'], true)
                    ? $editTab
                    : 'bulk'
            );
    }

    /**
     * Delete campaign: queue job to remove remote blogroll entries and delete all local data.
     */
    public function destroy(string $id)
    {
        $campaign = ScheduleSidebarCampaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        DeleteScheduleSidebarCampaignJob::dispatch($campaign->id)->onQueue('schedule_sidebar_deletions');

        return redirect()
            ->route('admin.schedule.sidebar.campaign.index')
            ->with('cus__success', 'Campaign deletion queued.');
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

    private function parseManualLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $lines = array_map(static fn ($line) => trim((string) $line), $lines ?: []);

        return $lines;
    }

    /**
     * Remove campaign data from this application only. Remote blogroll entries are not deleted.
     */
    public function purgeLocalOnly(string $id)
    {
        $campaign = ScheduleSidebarCampaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }
        $this->authorizeCampaignAccess($campaign);
        PurgeLocalCampaignDataService::purgeScheduleSidebarCampaign((int) $campaign->id);

        return redirect()
            ->route('admin.schedule.sidebar.campaign.index')
            ->with(
                'cus__success',
                'Campaign removed from this dashboard only. Remote blogroll links were not deleted.'
            );
    }

    /**
     * Remove multiple schedule sidebar campaigns from the database only (no remote API calls).
     */
    public function bulkPurgeLocal(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return back()->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, ScheduleSidebarCampaign::class);
        if ($allowed === []) {
            return back()->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        foreach ($allowed as $id) {
            PurgeLocalCampaignDataService::purgeScheduleSidebarCampaign($id);
        }

        $n = count($allowed);

        return redirect()
            ->route('admin.schedule.sidebar.campaign.index')
            ->with('cus__success', $n.' campaign(s) removed from this dashboard only. Remote blogroll links were not deleted.');
    }

    /**
     * Bulk retry all failed tasks across selected schedule sidebar campaigns.
     */
    public function bulkRetryFailed(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return back()->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, ScheduleSidebarCampaign::class);
        if ($allowed === []) {
            return back()->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        BulkRetryScheduleSidebarCampaignTasksJob::dispatch($allowed);

        $n = count($allowed);

        return redirect()
            ->route('admin.schedule.sidebar.campaign.index')
            ->with('cus__success', 'Bulk retry queued for '.$n.' schedule sidebar campaign(s). All failed tasks will be retried in the background. Run the queue worker to process them.');
    }

    /**
     * Retry a single schedule sidebar task (queued/failed/publishing).
     */
    public function retryTask(int $id)
    {
        $task = ScheduleSidebarCampaignTask::with('campaign')->findOrFail($id);

        $cacheKey = 'schedule_sidebar_retry_task_'.$task->id;
        if (Cache::has($cacheKey)) {
            return back()->with('cus__error', 'Retry was used recently. Please wait 3 minutes.');
        }

        if (! in_array($task->status, ['queued', 'failed', 'publishing'], true)) {
            return back()->with('cus__error', 'Only failed, queued or stuck tasks can be retried.');
        }

        Cache::put($cacheKey, true, now()->addMinutes(3));

        $wasFailed = $task->status === 'failed';
        $campaign = $task->campaign;

        $task->update([
            'status' => 'queued',
            'last_error' => null,
            'next_retry_at' => null,
            'locked_at' => null,
            'lock_token' => null,
        ]);

        if ($campaign) {
            $counters = app(ScheduleSidebarCampaignTargetCounterService::class);
            if ($wasFailed) {
                $counters->accountForFailedTaskRetries($campaign, 1);
            }
            $counters->syncCampaignFromTasks($campaign->fresh());
        }

        PublishScheduledSidebarBlogrollJob::dispatch($task->id, (int) ($task->dispatch_generation ?? 0))->onQueue('scheduled_sidebar_campaigns');

        return back()->with('cus__success', 'Task queued for retry.');
    }

    /**
     * Show form to edit single schedule sidebar task (keyword/link) – only for published (remote_id).
     */
    public function editTask(int $id)
    {
        $task = ScheduleSidebarCampaignTask::with(['campaign', 'domain.domain', 'link'])->findOrFail($id);

        if (empty($task->remote_id)) {
            return back()->with('cus__error', 'Task has no remote_id; only published links can be edited.');
        }

        return view('admin.campaigns.pbn-sidebar.edit-schedule-sidebar-task', compact('task'));
    }

    /**
     * Update single schedule sidebar task on remote and in DB.
     */
    public function updateTask(Request $request, int $id)
    {
        $task = ScheduleSidebarCampaignTask::with(['domain.domain', 'link'])->find($id);
        if (! $task || ! $task->link) {
            return back()->with('cus__error', 'Task or link not found');
        }

        if (! $task->remote_id) {
            return back()->with('cus__error', 'Task has no remote_id; cannot update on remote.');
        }

        $domain = $task->domain?->domain;
        if (! $domain || ! $domain->api_key) {
            return back()->with('cus__error', 'Domain or API key missing');
        }

        $request->validate([
            'keyword' => 'required|string|max:500',
            'link' => 'required|url|max:500',
        ]);

        $keyword = trim($request->keyword);
        $link = trim($request->link);

        $res = BlogrollApiService::updateEntryByRemoteId($domain->name, $domain->api_key, (string) $task->remote_id, $keyword, $link);
        if (! $res->successful()) {
            return back()->with('cus__error', 'Remote update failed: '.$res->body());
        }

        $task->link->update([
            'anchor_keyword' => $keyword,
            'target_url' => $link,
        ]);

        return redirect()
            ->route('admin.schedule.sidebar.campaign.show', $task->schedule_sidebar_campaign_id)
            ->with('cus__success', 'Sidebar link updated on remote and in database.');
    }

    /**
     * Delete single schedule sidebar task (remote + DB); decrement campaign counters.
     */
    public function deleteTask(int $id)
    {
        $task = ScheduleSidebarCampaignTask::with(['campaign', 'domain.domain', 'link'])->find($id);
        if (! $task) {
            return back()->with('cus__error', 'Task not found.');
        }

        $campaign = $task->campaign;
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found.');
        }

        if ($task->remote_id) {
            $domain = $task->domain?->domain;
            if ($domain && $domain->api_key) {
                $res = BlogrollApiService::deleteEntryByRemoteId($domain->name, $domain->api_key, (string) $task->remote_id);
                if (! $res->successful()) {
                    return back()->with('cus__error', 'Remote delete failed: '.$res->body());
                }
            }
        }

        $campaignDomainId = $task->schedule_sidebar_campaign_domain_id;
        $linkId = $task->schedule_sidebar_campaign_link_id;
        $task->delete();
        if ($linkId) {
            ScheduleSidebarCampaignLink::where('id', $linkId)->delete();
        }

        // If this campaign domain has no remaining tasks, remove it so domains count updates
        if ($campaignDomainId && ScheduleSidebarCampaignTask::where('schedule_sidebar_campaign_domain_id', $campaignDomainId)->count() === 0) {
            ScheduleSidebarCampaignDomain::where('id', $campaignDomainId)->delete();
        }

        app(ScheduleSidebarCampaignTargetCounterService::class)->syncCampaignFromTasks($campaign->fresh());

        return back()->with('cus__success', 'Sidebar link removed from campaign and remote site.');
    }
}
