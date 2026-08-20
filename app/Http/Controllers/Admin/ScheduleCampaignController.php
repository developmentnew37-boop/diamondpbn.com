<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientCampaignArticlesException;
use App\Http\Controllers\Admin\Concerns\AppliesCampaignListStatusFilter;
use App\Http\Controllers\Admin\Concerns\AppliesSuperAdminCampaignOwnerFilter;
use App\Http\Controllers\Admin\Concerns\AuthorizesAdminCampaign;
use App\Http\Controllers\Admin\Concerns\ProvidesLocalClientsForForms;
use App\Http\Controllers\Admin\Concerns\ValidatesBulkCampaignIds;
use App\Http\Controllers\Controller;
use App\Jobs\BulkRetryScheduleCampaignPostsJob;
use App\Jobs\BulkUpdateScheduleCampaignPostsJob;
use App\Jobs\DeleteScheduleCampaignJob;
use App\Jobs\PublishScheduledCampaignPostJob;
use App\Jobs\SyncConvertedCampaignRemoteStatusJob;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\ArticleLanguage;
use App\Models\Admin\ArticleSet;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignArticle;
use App\Models\Admin\ScheduleCampaignDate;
use App\Models\Admin\ScheduleCampaignDomain;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\CampaignArticleReservationService;
use App\Services\CampaignKeywordPairValidator;
use App\Services\ConvertedPostRemoteSyncService;
use App\Services\EditCampaignMultiLevelKeywordState;
use App\Services\LiveTaskDomainReplacement\LiveTaskBulkDomainReplacementService;
use App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile;
use App\Services\LocalClientBillingService;
use App\Services\PurgeLocalCampaignDataService;
use App\Support\CampaignTaskStatusFilter;
use App\Support\WordPressApiFetchedPost;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ScheduleCampaignController extends Controller
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
        $request->validate([
            'search' => 'nullable|string|max:150',
            'filter_user' => 'nullable|string|max:20',
            'status' => $this->campaignListStatusValidationRule(),
        ]);

        $limit = config('campaign.pagination.default_limit');

        $query = ScheduleCampaign::query()
            ->where('is_sticky_campaign', false)
            ->with([
                'domainCategory',
            ]);

        // 🔍 Search by campaign no
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
                    LiveTaskReplacementProfile::schedulePost(),
                    $campaigns->pluck('id')->all(),
                );
        }

        return view(
            'admin.campaigns.pbn-post.schedule-campaign',
            array_merge(compact('campaigns', 'offset', 'replaceableCampaignIds'), ['isStickySchedule' => false], $ownerData)
        );
    }

    /**
     * Scheduled sticky post campaigns (same flow as schedule post, is_sticky on API).
     */
    public function indexSticky(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:150',
            'filter_user' => 'nullable|string|max:20',
        ]);

        $limit = config('campaign.pagination.default_limit');

        $query = ScheduleCampaign::query()
            ->where('is_sticky_campaign', true)
            ->with([
                'domainCategory',
            ]);

        if ($request->filled('search')) {
            $query->where(
                'campaign_no',
                'LIKE',
                '%'.trim($request->search).'%'
            );
        }
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
                    LiveTaskReplacementProfile::schedulePost(),
                    $campaigns->pluck('id')->all(),
                );
        }

        return view(
            'admin.campaigns.pbn-post.schedule-campaign',
            array_merge(compact('campaigns', 'offset', 'replaceableCampaignIds'), ['isStickySchedule' => true], $ownerData)
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $campaignId = 'SCH-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        $articleCategory = ArticleCategory::all();
        // // ** now fetching the user the articles ** //
        $articleSet = ArticleSet::withCount('articles')->where('admin_id', auth('admin')->id())->get();
        // ** now providing user domain sets
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();
        // ** article languages with article count
        $articleLanguages = ArticleLanguage::withCount(['Article' => function ($query) {
            $query->where('status', 0)
                ->whereNull('deleted_at')
                ->whereNull('lock_at');
        }])->having('article_count', '>', 0)->get();

        // return view('admin.campaigns.pbn-post.create-campaign',);
        return view(
            'admin.campaigns.pbn-post.create-schedule-campaign',
            array_merge(
                compact('campaignId', 'domainCategory', 'articleCategory', 'articleSet', 'domainSets', 'articleLanguages'),
                [
                    'isStickySchedule' => false,
                    'localClients' => $this->activeLocalClientsForForms(),
                ]
            )
        );
    }

    public function createSticky()
    {
        $campaignId = 'SST-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        $domainCategory = DomainCategory::all();
        $articleCategory = ArticleCategory::all();
        $articleSet = ArticleSet::withCount('articles')->where('admin_id', auth('admin')->id())->get();
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();
        $articleLanguages = ArticleLanguage::withCount(['Article' => function ($query) {
            $query->where('status', 0)
                ->whereNull('deleted_at')
                ->whereNull('lock_at');
        }])->having('article_count', '>', 0)->get();

        return view(
            'admin.campaigns.pbn-post.create-schedule-campaign',
            array_merge(
                compact('campaignId', 'domainCategory', 'articleCategory', 'articleSet', 'domainSets', 'articleLanguages'),
                [
                    'isStickySchedule' => true,
                    'localClients' => $this->activeLocalClientsForForms(),
                ]
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(Request $request)
    // {
    //     //

    // ----------------------------------

    private function generateUniqueCampaignNo(string $input, ?int $excludeId = null): string
    {
        $base = Str::slug($input);
        if ($base === '') {
            $base = 'campaign-'.now()->timestamp;
        }
        $slug = $base;
        $counter = 1;
        do {
            $query = ScheduleCampaign::where('campaign_no', $slug);
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
            $postQty = 0;
            $dateMin = null;
            $dateMax = null;
            foreach ($dateRows as $row) {
                $q = (int) ($row['quantity'] ?? 0);
                if ($q > 0) {
                    $d = $row['date'] ?? null;
                    if ($d) {
                        $postQty += $q;
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
            if ($postQty < 1 || $dateMin === null || $dateMax === null) {
                return back()->with('cus__error', 'Date distribution must have at least one date with quantity > 0.')->withInput();
            }
            $scheduleFrom = $dateMin->format('Y-m-d');
            $scheduleTo = $dateMax->format('Y-m-d');
        } else {
            $request->validate([
                'post_quantity' => 'required|integer|min:1',
                'schedule_from_date' => 'required|date',
                'schedule_to_date' => 'required|date|after_or_equal:schedule_from_date',
            ]);
            $postQty = (int) $request->post_quantity;
            $scheduleFrom = $request->schedule_from_date;
            $scheduleTo = $request->schedule_to_date;
        }

        $validated = $request->validate([
            'campaign_no' => 'required|string',
            'domain_category' => 'nullable|integer|exists:domain_categories,id',
            'article_niche' => 'nullable|integer|exists:article_categories,id',
            'sel_articles_opt' => 'required|in:own_article,system_article,language_article',
            'selected_articles_val' => 'required|string',
            'keywordmethod' => 'nullable|in:normal,bulk,multiple,multi_bulk,raw_html',
            'keywordsDataHolder' => 'required|string',
            'campaigns_domains' => 'required|string',
            'local_client_id' => 'nullable|integer|exists:local_clients,id',
            'billing_currency' => ['nullable', 'string', Rule::in(\App\Support\CurrencyFormatter::supportedCodes())],
        ]);

        $articleIds = array_values(array_filter(
            array_map('intval', explode(',', $request->selected_articles_val))
        ));
        $domainIds = json_decode($request->campaigns_domains, true);
        if (! is_array($domainIds)) {
            return back()->with('cus__error', 'Invalid domains JSON')->withInput();
        }
        $domainIds = array_values(array_map('intval', $domainIds));
        $keywords = json_decode($request->keywordsDataHolder, true);
        if (! is_array($keywords)) {
            return back()->with('cus__error', 'Invalid keywords JSON')->withInput();
        }

        $method = (string) ($request->keywordmethod ?? 'normal');
        $isMultiple = in_array($method, ['multiple', 'multi_bulk', 'raw_html'], true);

        if (count($articleIds) !== $postQty || count($domainIds) !== $postQty || count($keywords) !== $postQty) {
            return back()
                ->with('cus__error', 'Articles, domains, and keywords count must equal total post quantity ('.$postQty.').')
                ->withInput();
        }

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        $isStickySchedule = $request->boolean('is_sticky_campaign');

        $substitutionCount = 0;

        try {
            if ($useDateTable) {
                $substitutionCount = $this->storeWithDateTable(
                    $request,
                    $campaignNo,
                    $postQty,
                    $scheduleFrom,
                    $scheduleTo,
                    $articleIds,
                    $domainIds,
                    $keywords,
                    $dateRows,
                    $isMultiple,
                    $isStickySchedule,
                    $method
                );
            } else {
                $from = Carbon::parse($scheduleFrom)->startOfDay();
                $to = Carbon::parse($scheduleTo)->startOfDay();
                $totalDays = $from->diffInDays($to) + 1;
                $perDay = intdiv($postQty, $totalDays);
                $remainder = $postQty % $totalDays;
                $substitutionCount = $this->storeWithDateRange(
                    $request,
                    $campaignNo,
                    $postQty,
                    $articleIds,
                    $domainIds,
                    $keywords,
                    $from,
                    $totalDays,
                    $perDay,
                    $remainder,
                    $isMultiple,
                    $isStickySchedule,
                    $method
                );
            }
        } catch (InsufficientCampaignArticlesException $e) {
            return back()->with('cus__error', $e->getMessage())->withInput();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('cus__error', 'Could not create scheduled campaign: '.$e->getMessage())->withInput();
        }

        $redirectRoute = $isStickySchedule
            ? 'admin.schedule.sticky.campaign.index'
            : 'admin.schedule.campaign.create';

        $successMessage = 'Scheduled campaign created successfully.';
        if ($substitutionCount > 0) {
            $successMessage .= " {$substitutionCount} article(s) were auto-replaced because they were already in use.";
        }

        return redirect()
            ->route($redirectRoute)
            ->with('cus__success', $successMessage);
    }

    /**
     * @return array{article: Article, substituted: bool}
     */
    private function reserveScheduleArticle(
        CampaignArticleReservationService $reservationService,
        Request $request,
        int $requestedArticleId,
        array &$reservedArticleIds,
    ): array {
        $result = $reservationService->reserve(
            $requestedArticleId,
            (string) $request->sel_articles_opt,
            CampaignArticleReservationService::contextFromRequest($request),
            $reservedArticleIds,
            markStatusUsed: false,
        );

        $reservedArticleIds[] = $result['article']->id;

        return $result;
    }

    /**
     * Store campaign using per-date quantity table (date_quantities).
     *
     * @return int Number of auto-substituted articles
     */
    private function storeWithDateTable(
        Request $request,
        string $campaignNo,
        int $postQty,
        string $scheduleFrom,
        string $scheduleTo,
        array $articleIds,
        array $domainIds,
        array $keywords,
        array $dateRows,
        bool $isMultiple,
        bool $isStickySchedule = false,
        string $method = 'normal'
    ): int {
        $substitutionCount = 0;
        $reservationService = app(CampaignArticleReservationService::class);

        DB::transaction(function () use (
            $request,
            $campaignNo,
            $postQty,
            $scheduleFrom,
            $scheduleTo,
            $articleIds,
            $domainIds,
            $keywords,
            $dateRows,
            $isMultiple,
            $isStickySchedule,
            $method,
            $reservationService,
            &$substitutionCount,
        ) {
            $campaign = ScheduleCampaign::create([
                'campaign_no' => $campaignNo,
                'domain_category_id' => $request->domain_category,
                'article_category_id' => $request->article_niche,
                'admin_id' => auth('admin')->id(),
                'schedule_from_date' => $scheduleFrom,
                'schedule_to_date' => $scheduleTo,
                'status' => 'queued',
                'total_targets' => $postQty,
                'is_sticky_campaign' => $isStickySchedule,
                'completed_targets' => 0,
                'failed_targets' => 0,
            ]);

            foreach ($domainIds as $i => $domainId) {
                ScheduleCampaignDomain::create([
                    'schedule_campaign_id' => $campaign->id,
                    'domain_id' => $domainId,
                ]);
            }
            $domainMap = $campaign->domains()->orderBy('id')->pluck('id')->all();

            $articleMap = [];
            $reservedArticleIds = [];
            foreach ($articleIds as $i => $articleId) {
                $row = $keywords[$i] ?? [];
                $kwVal = $row['keyword'] ?? null;
                $urlVal = $row['url'] ?? null;
                if ($isMultiple) {
                    $kwArr = is_array($kwVal) ? $kwVal : (is_null($kwVal) ? [] : [$kwVal]);
                    $urlArr = is_array($urlVal) ? $urlVal : (is_null($urlVal) ? [] : [$urlVal]);

                    // Handle multi-bulk additional_links
                    if ($method === 'multi_bulk' && isset($row['additional_links']) && is_array($row['additional_links'])) {
                        foreach ($row['additional_links'] as $additionalLink) {
                            if (isset($additionalLink['keyword'])) {
                                $kwArr[] = $additionalLink['keyword'];
                            }
                            if (isset($additionalLink['url'])) {
                                $urlArr[] = $additionalLink['url'];
                            }
                        }
                    }

                    $kwArr = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $kwArr)));
                    $urlArr = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $urlArr)));
                    $kwStore = json_encode($kwArr, JSON_UNESCAPED_UNICODE);
                    $urlStore = json_encode($urlArr, JSON_UNESCAPED_UNICODE);
                    $kwType = 'json';
                    $urlType = 'json';
                } else {
                    $kwStore = is_array($kwVal) ? ($kwVal[0] ?? null) : $kwVal;
                    $urlStore = is_array($urlVal) ? ($urlVal[0] ?? null) : $urlVal;
                    $kwStore = $kwStore !== null ? trim((string) $kwStore) : null;
                    $urlStore = $urlStore !== null ? trim((string) $urlStore) : null;
                    if ($kwStore === '') {
                        $kwStore = null;
                    }
                    if ($urlStore === '') {
                        $urlStore = null;
                    }
                    $kwType = 'single';
                    $urlType = 'single';
                }
                $reservation = $this->reserveScheduleArticle(
                    $reservationService,
                    $request,
                    (int) $articleId,
                    $reservedArticleIds,
                );
                $articleRow = $reservation['article'];
                if ($reservation['substituted']) {
                    $substitutionCount++;
                }

                $sca = ScheduleCampaignArticle::create([
                    'schedule_campaign_id' => $campaign->id,
                    'article_id' => $articleRow->id,
                    'article_title_snapshot' => $articleRow->name,
                    'article_body_snapshot' => $articleRow->description,
                    'keyword' => $kwStore,
                    'url' => $urlStore,
                    'keyword_type' => $kwType,
                    'url_type' => $urlType,
                    'media' => $row['media'] ?? null,
                    'nofollow' => ! empty($row['nofollow']),
                    'sponsored' => ! empty($row['sponsored']),
                    'ugc' => ! empty($row['ugc']),
                    'noopener' => ! empty($row['noopener']),
                    'noreferrer' => ! empty($row['noreferrer']),
                    'raw_rel_attr' => ! empty($row['raw_rel_attr']) ? trim($row['raw_rel_attr']) : null,
                ]);
                $articleMap[$i] = $sca->id;
            }

            foreach ($dateRows as $dr) {
                $q = (int) ($dr['quantity'] ?? 0);
                $d = $dr['date'] ?? null;
                if ($q < 1 || ! $d) {
                    continue;
                }
                ScheduleCampaignDate::create([
                    'schedule_campaign_id' => $campaign->id,
                    'schedule_date' => $d,
                    'quantity' => $q,
                ]);
            }

            $postRows = [];
            $now = now();
            $globalIndex = 0;
            $dateRowsOrdered = ScheduleCampaignDate::where('schedule_campaign_id', $campaign->id)->orderBy('schedule_date')->get();
            foreach ($dateRowsOrdered as $dateRow) {
                $scheduleAt = Carbon::parse($dateRow->schedule_date)->startOfDay();
                for ($k = 0; $k < $dateRow->quantity; $k++) {
                    if ($globalIndex >= $postQty) {
                        break;
                    }
                    $postRows[] = [
                        'schedule_campaign_id' => $campaign->id,
                        'schedule_campaign_domain_id' => $domainMap[$globalIndex],
                        'schedule_campaign_article_id' => $articleMap[$globalIndex],
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
                ScheduleCampaignPost::insert($chunk);
            }

            app(LocalClientBillingService::class)->applyFromRequest(
                $request,
                $campaign,
                $domainIds,
                'schedule_post',
                $isStickySchedule,
            );
        });

        return $substitutionCount;
    }

    /**
     * Store campaign using from/to date range and even distribution (legacy).
     *
     * @return int Number of auto-substituted articles
     */
    private function storeWithDateRange(
        Request $request,
        string $campaignNo,
        int $postQty,
        array $articleIds,
        array $domainIds,
        array $keywords,
        $from,
        int $totalDays,
        int $perDay,
        int $remainder,
        bool $isMultiple,
        bool $isStickySchedule = false,
        string $method = 'normal'
    ): int {
        $substitutionCount = 0;
        $reservationService = app(CampaignArticleReservationService::class);

        DB::transaction(function () use (
            $request,
            $campaignNo,
            $postQty,
            $articleIds,
            $domainIds,
            $keywords,
            $from,
            $perDay,
            $remainder,
            $isMultiple,
            $isStickySchedule,
            $method,
            $reservationService,
            &$substitutionCount,
        ) {
            $campaign = ScheduleCampaign::create([
                'campaign_no' => $campaignNo,
                'domain_category_id' => $request->domain_category,
                'article_category_id' => $request->article_niche,
                'admin_id' => auth('admin')->id(),
                'schedule_from_date' => $request->schedule_from_date,
                'schedule_to_date' => $request->schedule_to_date,
                'status' => 'queued',
                'total_targets' => $postQty,
                'is_sticky_campaign' => $isStickySchedule,
                'completed_targets' => 0,
                'failed_targets' => 0,
            ]);

            $domainMap = [];
            foreach ($domainIds as $i => $domainId) {
                $row = ScheduleCampaignDomain::create([
                    'schedule_campaign_id' => $campaign->id,
                    'domain_id' => $domainId,
                ]);
                $domainMap[$i] = $row->id;
            }

            $articleMap = [];
            $reservedArticleIds = [];
            foreach ($articleIds as $i => $articleId) {
                $row = $keywords[$i] ?? [];
                $kwVal = $row['keyword'] ?? null;
                $urlVal = $row['url'] ?? null;
                if ($isMultiple) {
                    $kwArr = is_array($kwVal) ? $kwVal : (is_null($kwVal) ? [] : [$kwVal]);
                    $urlArr = is_array($urlVal) ? $urlVal : (is_null($urlVal) ? [] : [$urlVal]);

                    // Handle multi-bulk additional_links
                    if ($method === 'multi_bulk' && isset($row['additional_links']) && is_array($row['additional_links'])) {
                        foreach ($row['additional_links'] as $additionalLink) {
                            if (isset($additionalLink['keyword'])) {
                                $kwArr[] = $additionalLink['keyword'];
                            }
                            if (isset($additionalLink['url'])) {
                                $urlArr[] = $additionalLink['url'];
                            }
                        }
                    }

                    $kwArr = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $kwArr)));
                    $urlArr = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $urlArr)));
                    $kwStore = json_encode($kwArr, JSON_UNESCAPED_UNICODE);
                    $urlStore = json_encode($urlArr, JSON_UNESCAPED_UNICODE);
                    $kwType = 'json';
                    $urlType = 'json';
                } else {
                    $kwStore = is_array($kwVal) ? ($kwVal[0] ?? null) : $kwVal;
                    $urlStore = is_array($urlVal) ? ($urlVal[0] ?? null) : $urlVal;
                    $kwStore = $kwStore !== null ? trim((string) $kwStore) : null;
                    $urlStore = $urlStore !== null ? trim((string) $urlStore) : null;
                    if ($kwStore === '') {
                        $kwStore = null;
                    }
                    if ($urlStore === '') {
                        $urlStore = null;
                    }
                    $kwType = 'single';
                    $urlType = 'single';
                }
                $reservation = $this->reserveScheduleArticle(
                    $reservationService,
                    $request,
                    (int) $articleId,
                    $reservedArticleIds,
                );
                $articleRow = $reservation['article'];
                if ($reservation['substituted']) {
                    $substitutionCount++;
                }

                $sca = ScheduleCampaignArticle::create([
                    'schedule_campaign_id' => $campaign->id,
                    'article_id' => $articleRow->id,
                    'article_title_snapshot' => $articleRow->name,
                    'article_body_snapshot' => $articleRow->description,
                    'keyword' => $kwStore,
                    'url' => $urlStore,
                    'keyword_type' => $kwType,
                    'url_type' => $urlType,
                    'media' => $row['media'] ?? null,
                    'nofollow' => ! empty($row['nofollow']),
                    'sponsored' => ! empty($row['sponsored']),
                    'ugc' => ! empty($row['ugc']),
                    'noopener' => ! empty($row['noopener']),
                    'noreferrer' => ! empty($row['noreferrer']),
                    'raw_rel_attr' => ! empty($row['raw_rel_attr']) ? trim($row['raw_rel_attr']) : null,
                ]);
                $articleMap[$i] = $sca->id;
            }

            $rows = [];
            $now = now();
            for ($i = 0; $i < $postQty; $i++) {
                if ($i < ($perDay + 1) * $remainder) {
                    $dayIndex = intdiv($i, $perDay + 1);
                } else {
                    $dayIndex = $remainder + intdiv($i - ($perDay + 1) * $remainder, $perDay);
                }
                $scheduleAt = $from->copy()->addDays($dayIndex)->setTime(0, 0);
                $rows[] = [
                    'schedule_campaign_id' => $campaign->id,
                    'schedule_campaign_domain_id' => $domainMap[$i],
                    'schedule_campaign_article_id' => $articleMap[$i],
                    'schedule_at' => $scheduleAt,
                    'status' => 'queued',
                    'attempt_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($rows) === 200) {
                    ScheduleCampaignPost::insert($rows);
                    $rows = [];
                }
            }
            if (! empty($rows)) {
                ScheduleCampaignPost::insert($rows);
            }

            app(LocalClientBillingService::class)->applyFromRequest(
                $request,
                $campaign,
                $domainIds,
                'schedule_post',
                $isStickySchedule,
            );
        });

        return $substitutionCount;
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id, ConvertedPostRemoteSyncService $remoteSync)
    {
        // 🔐 Fetch scheduled campaign
        $campaign = ScheduleCampaign::with(['sourceCampaign', 'localClient'])->findOrFail($id);

        if (filled($campaign->converted_from_campaign_id)) {
            $remoteSync->syncCampaignPosts($campaign);
            $campaign->refresh();

            $stillNeedsSync = ScheduleCampaignPost::query()
                ->with(['campaignDomain.domain'])
                ->where('schedule_campaign_id', $campaign->id)
                ->where('is_converted_live', true)
                ->get()
                ->contains(fn (ScheduleCampaignPost $post) => $remoteSync->needsRemoteSync($post));

            if ($stillNeedsSync) {
                SyncConvertedCampaignRemoteStatusJob::dispatch((int) $campaign->id)
                    ->onQueue('campaign_conversions');
            }
        }

        $limit = 100;
        $statusFilter = $request->string('status')->toString();
        $postsQuery = ScheduleCampaignPost::query()->where('schedule_campaign_id', $campaign->id);
        $isConvertedLiveCampaign = filled($campaign->converted_from_campaign_id);

        if ($isConvertedLiveCampaign) {
            $statusCounts = CampaignTaskStatusFilter::countsForConvertedLivePosts($postsQuery);
            $postsBaseQuery = ScheduleCampaignPost::with([
                'campaignDomain.domain',
                'campaignArticle.article',
            ])->where('schedule_campaign_id', $campaign->id);

            $campaignPost = CampaignTaskStatusFilter::applyForConvertedLivePost(
                $postsBaseQuery,
                $statusFilter !== '' ? $statusFilter : null,
            )
                ->orderBy('schedule_at')
                ->orderBy('id')
                ->paginate($limit)
                ->withQueryString();
        } else {
            $statusCounts = CampaignTaskStatusFilter::counts($postsQuery);
            $campaignPost = CampaignTaskStatusFilter::apply(
                ScheduleCampaignPost::with([
                    'campaignDomain.domain',
                    'campaignArticle.article',
                ])
                    ->where('schedule_campaign_id', $campaign->id),
                $statusFilter
            )
                ->orderBy('schedule_at')
                ->orderBy('id')
                ->paginate($limit)
                ->withQueryString();
        }

        $offset = ($campaignPost->currentPage() - 1) * $limit;

        $recentReplacements = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('schedule_campaign_domain_replacements')) {
            $recentReplacements = \App\Models\Admin\ScheduleCampaignDomainReplacement::query()
                ->where('schedule_campaign_id', $campaign->id)
                ->whereIn('state', ['dispatch_pending', 'dispatching', 'dispatch_failed', 'completed'])
                ->latest()
                ->limit(10)
                ->get();
        }

        return view(
            'admin.campaigns.pbn-post.view-schedule-campaign',
            compact('campaign', 'campaignPost', 'offset', 'statusFilter', 'statusCounts', 'isConvertedLiveCampaign', 'recentReplacements')
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function report(string $campaign_no, string $token, ConvertedPostRemoteSyncService $remoteSync)
    {
        // 🔐 1️⃣ Validate schedule campaign via token
        $campaign = ScheduleCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        if (filled($campaign->converted_from_campaign_id)) {
            $remoteSync->syncCampaignPosts($campaign);
        }

        // 📊 2️⃣ Aggregate stats
        $stats = ScheduleCampaignPost::where('schedule_campaign_id', $campaign->id)
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

        // 📦 3️⃣ Fetch all posts (NO pagination for report)
        $posts = ScheduleCampaignPost::with([
            'campaignDomain.domain',
            'campaignArticle',
        ])
            ->where('schedule_campaign_id', $campaign->id)
            ->orderBy('schedule_at')
            ->orderBy('id')
            ->get();

        // 🔎 4️⃣ Max keyword/URL pairs across all posts (mixed single + multi-link campaigns)
        $maxKeywordCount = 1;
        foreach ($posts as $post) {
            $ca = $post->campaignArticle;
            if (! $ca) {
                continue;
            }
            if (($ca->keyword_type ?? 'single') === 'json') {
                $n = count(json_decode($ca->keyword ?? '[]', true) ?? []);
            } else {
                $n = (($ca->keyword ?? '') !== '' || ($ca->url ?? '') !== '') ? 1 : 0;
            }
            $maxKeywordCount = max($maxKeywordCount, $n);
        }
        $maxKeywordCount = max(1, $maxKeywordCount);

        // Table layout: multi columns when any row needs more than one pair (same idea as export)
        $keywordType = $maxKeywordCount > 1 ? 'json' : 'single';

        return view(
            'admin.campaigns.pbn-post.schedule-campaign-report',
            compact(
                'campaign',
                'stats',
                'successRate',
                'posts',
                'keywordType',
                'maxKeywordCount'
            )
        );
    }

    // ** Export report //

    public function exportReport(string $campaign_no, string $token)
    {
        // 🔐 Validate schedule campaign via token
        $campaign = ScheduleCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        // 📦 Fetch scheduled posts (IMPORTANT: correct relations)
        $posts = ScheduleCampaignPost::with([
            'campaignDomain.domain',
            'campaignArticle',
        ])
            ->where('schedule_campaign_id', $campaign->id)
            ->orderBy('schedule_at')
            ->orderBy('id')
            ->get();

        /* =========================================================
       1️⃣ DETECT MAX KEYWORD / URL PAIRS
       ========================================================= */
        $maxPairs = 0;

        foreach ($posts as $post) {
            $ca = $post->campaignArticle;

            if (! $ca) {
                continue;
            }

            if ($ca->keyword_type === 'json') {
                $count = count(json_decode($ca->keyword ?? '[]', true));
            } else {
                $count = ($ca->keyword || $ca->url) ? 1 : 0;
            }

            $maxPairs = max($maxPairs, $count);
        }

        $maxPairs = max($maxPairs, 1);

        /* =========================================================
       2️⃣ BUILD HEADERS (NO STICKY COLUMN)
       ========================================================= */
        $headers = [
            'S.No',
            'Domain',
            'BlogPost',
            'Scheduled At',
        ];

        for ($i = 1; $i <= $maxPairs; $i++) {
            if ($i === 1) {
                $headers[] = 'Keyword';
                $headers[] = 'URL';
            } else {
                $headers[] = "Keyword {$i}";
                $headers[] = "URL {$i}";
            }
        }

        $headers[] = 'Status';
        $headers[] = 'Published Date';

        /* =========================================================
       3️⃣ CREATE EXCEL
       ========================================================= */
        $writer = SimpleExcelWriter::streamDownload(
            "schedule-campaign-report-{$campaign_no}.xlsx"
        )->addHeader($headers);

        /* =========================================================
       4️⃣ FILL ROWS
       ========================================================= */
        $sno = 1;

        foreach ($posts as $post) {

            $row = [
                'S.No' => $sno++,
                'Domain' => optional($post->campaignDomain?->domain)->name ?? '-',
                'BlogPost' => $post->remote_url ?? '-',
                'Scheduled At' => optional($post->schedule_at)?->format('d M Y H:i') ?? '-',
            ];

            $ca = $post->campaignArticle;

            if ($ca && $ca->keyword_type === 'json') {
                $keywords = json_decode($ca->keyword ?? '[]', true) ?? [];
                $urls = json_decode($ca->url ?? '[]', true) ?? [];
            } else {
                $keywords = [$ca->keyword ?? ''];
                $urls = [$ca->url ?? ''];
            }

            for ($i = 0; $i < $maxPairs; $i++) {
                if ($i === 0) {
                    $row['Keyword'] = $keywords[0] ?? '';
                    $row['URL'] = $urls[0] ?? '';
                } else {
                    $row['Keyword '.($i + 1)] = $keywords[$i] ?? '';
                    $row['URL '.($i + 1)] = $urls[$i] ?? '';
                }
            }

            // $row['Status'] = $post->status === 'success' ? 'Live' : ucfirst($post->status);
            $row['Status'] = $post->status === 'success' ? 'Live' : 'Not Live';
            $row['Published Date'] = optional($post->published_at)?->format('d M Y') ?? '-';

            $writer->addRow($row);
        }

        return $writer->toBrowser();
    }

    /**
     * Show the form for editing the campaign (campaign_no + keyword/URL batches).
     */
    public function edit(string $id)
    {
        $campaign = ScheduleCampaign::findOrFail($id);

        $articles = ScheduleCampaignArticle::with('posts')
            ->where('schedule_campaign_id', $campaign->id)
            ->get();

        $batches = [];
        $hasJsonKeywords = false;
        foreach ($articles as $ca) {
            $k = $ca->keyword === null ? '' : (string) $ca->keyword;
            $u = $ca->url === null ? '' : (string) $ca->url;
            $key = $k."\n".$u;
            if (($ca->keyword_type ?? 'single') === 'json') {
                $hasJsonKeywords = true;
            }

            if (! isset($batches[$key])) {
                $batches[$key] = [
                    'keyword' => $k,
                    'url' => $u,
                    'keyword_type' => $ca->keyword_type ?? 'single',
                    'url_type' => $ca->url_type ?? 'single',
                    'count' => 0,
                    'article_ids' => [],
                    'post_ids' => [],
                    'representative_id' => $ca->id,
                ];
            }
            $batches[$key]['count']++;
            $batches[$key]['article_ids'][] = $ca->id;
            foreach ($ca->posts as $post) {
                $batches[$key]['post_ids'][] = $post->id;
            }
        }
        foreach ($batches as &$batch) {
            $batch['post_ids'] = array_values(array_unique($batch['post_ids']));
        }
        unset($batch);
        $distinctBatches = array_values($batches);

        $orderedArticleIds = $this->orderedScheduleCampaignArticleIds($campaign);
        $postQuantity = count($orderedArticleIds);
        $articleById = ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)
            ->get(['id', 'keyword', 'url', 'keyword_type', 'url_type'])
            ->keyBy('id');
        $allLinksForBulk = [];
        foreach ($orderedArticleIds as $articleId) {
            $ca = $articleById->get($articleId);
            if (! $ca) {
                continue;
            }
            if (($ca->keyword_type ?? 'single') === 'json') {
                $kwList = json_decode((string) ($ca->keyword ?? '[]'), true);
                $urlList = json_decode((string) ($ca->url ?? '[]'), true);
                $keyword = trim((string) (($kwList[0] ?? '')));
                $url = trim((string) (($urlList[0] ?? '')));
            } else {
                $keyword = trim((string) ($ca->keyword ?? ''));
                $url = trim((string) ($ca->url ?? ''));
            }
            $allLinksForBulk[] = [
                'article_id' => (int) $ca->id,
                'keyword' => $keyword,
                'url' => $url,
            ];
        }
        $multiLevelBoxes = $postQuantity > 0
            ? EditCampaignMultiLevelKeywordState::buildBoxes(
                $orderedArticleIds,
                fn (int $id) => ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)->find($id),
                fn ($ca) => EditCampaignMultiLevelKeywordState::payloadFromKeywordColumns($ca)
            )
            : [];
        $initialNofollow = false;
        if ($postQuantity > 0) {
            $firstCa = ScheduleCampaignArticle::find($orderedArticleIds[0]);
            $initialNofollow = $firstCa && (bool) $firstCa->nofollow;
        }
        $scheduleIndexUrl = $campaign->is_sticky_campaign
            ? route('admin.schedule.sticky.campaign.index')
            : route('admin.schedule.campaign.index');
        $preferredKeywordTab = $hasJsonKeywords ? 'multi' : 'batch';

        return view(
            'admin.campaigns.pbn-post.edit-schedule-campaign',
            compact(
                'campaign',
                'distinctBatches',
                'postQuantity',
                'allLinksForBulk',
                'multiLevelBoxes',
                'initialNofollow',
                'scheduleIndexUrl',
                'preferredKeywordTab'
            )
        );
    }

    /**
     * Update campaign no.
     */
    public function update(Request $request, string $id)
    {
        $campaign = ScheduleCampaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        $validated = $request->validate(['campaign_no' => 'required|string|max:191']);

        $campaignNo = $this->generateUniqueCampaignNo($validated['campaign_no'], (int) $campaign->id);
        $campaign->update(['campaign_no' => $campaignNo]);

        return back()
            ->with('cus__success', 'Campaign updated.')
            ->with('edit_schedule_campaign_tab', 'campaign');
    }

    /**
     * Per-post keyword order (matches post creation: schedule_at, then id).
     *
     * @return array<int, int>
     */
    private function orderedScheduleCampaignArticleIds(ScheduleCampaign $campaign): array
    {
        return ScheduleCampaignPost::query()
            ->where('schedule_campaign_id', $campaign->id)
            ->orderBy('schedule_at')
            ->orderBy('id')
            ->pluck('schedule_campaign_article_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int>|null  $onlyArticleIds  When set, only these rows are updated (multi-level per-post).
     * @return array{error?: string, changed: bool, post_ids: array<int>}
     */
    private function applyScheduleCampaignKeywordBatch(
        ScheduleCampaign $campaign,
        ScheduleCampaignArticle $representative,
        $kwInput,
        $urlInput,
        ?array $onlyArticleIds = null
    ): array {
        if (is_array($kwInput) && is_array($urlInput)) {
            $newKwList = array_values(array_map(fn ($v) => trim((string) $v), $kwInput));
            $newUrlList = array_values(array_map(fn ($v) => trim((string) $v), $urlInput));
            $len = min(count($newKwList), count($newUrlList));
            $newPairs = [];
            for ($i = 0; $i < $len; $i++) {
                $newPairs[] = [$newKwList[$i] ?? '', $newUrlList[$i] ?? ''];
            }
        } else {
            $newKw = trim((string) $kwInput);
            $newUrl = trim((string) $urlInput);
            $newPairs = ($newKw !== '' || $newUrl !== '') ? [[$newKw, $newUrl]] : [];
        }

        if ($msg = CampaignKeywordPairValidator::validateEditPairs($newPairs)) {
            return ['error' => $msg, 'changed' => false, 'post_ids' => []];
        }

        $pairsToStore = array_values(array_filter(
            $newPairs,
            fn ($p) => trim((string) ($p[0] ?? '')) !== '' && trim((string) ($p[1] ?? '')) !== ''
        ));
        $newIsJson = count($pairsToStore) > 1;

        if (count($pairsToStore) === 0) {
            $kwStore = null;
            $urlStore = null;
        } elseif (count($pairsToStore) === 1 && ! $newIsJson) {
            $kwStore = $pairsToStore[0][0];
            $urlStore = $pairsToStore[0][1];
        } else {
            $kwStore = json_encode(array_column($pairsToStore, 0), JSON_UNESCAPED_UNICODE);
            $urlStore = json_encode(array_column($pairsToStore, 1), JSON_UNESCAPED_UNICODE);
        }
        $newKeywordType = $newIsJson ? 'json' : 'single';
        $newUrlType = $newIsJson ? 'json' : 'single';

        if ($representative->keyword === $kwStore && $representative->url === $urlStore
            && ($representative->keyword_type ?? 'single') === $newKeywordType
            && ($representative->url_type ?? 'single') === $newUrlType) {
            return ['changed' => false, 'post_ids' => []];
        }

        if ($onlyArticleIds !== null) {
            $articleIds = ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)
                ->whereIn('id', array_map('intval', $onlyArticleIds))
                ->pluck('id')
                ->values()
                ->all();
        } else {
            $articleIds = ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)
                ->where('keyword', $representative->keyword)
                ->where('url', $representative->url)
                ->pluck('id')
                ->all();
        }

        if (count($articleIds) === 0) {
            return ['changed' => false, 'post_ids' => []];
        }

        ScheduleCampaignArticle::whereIn('id', $articleIds)->update([
            'keyword' => $kwStore,
            'url' => $urlStore,
            'keyword_type' => $newKeywordType,
            'url_type' => $newUrlType,
        ]);

        $ids = ScheduleCampaignPost::whereIn('schedule_campaign_article_id', $articleIds)
            ->where('status', 'success')
            ->whereNotNull('remote_id')
            ->pluck('id')
            ->all();

        return ['changed' => true, 'post_ids' => $ids];
    }

    /**
     * Multi-level keyword update (same JSON shape as create schedule campaign, one row per post in schedule order).
     */
    public function multiLevelUpdateScheduleKeywords(Request $request, string $id)
    {
        $campaign = ScheduleCampaign::find($id);
        if (! $campaign) {
            return redirect()
                ->route('admin.schedule.campaign.index')
                ->with('cus__error', 'Campaign not found');
        }

        $request->validate([
            'keywordmethod' => 'required|in:multiple',
            'keywordsDataHolder' => 'required|string',
        ]);

        $keywords = json_decode((string) $request->keywordsDataHolder, true);
        if (! is_array($keywords)) {
            return redirect()
                ->route('admin.schedule.campaign.edit', $campaign->id)
                ->with('cus__error', 'Invalid keywords JSON.')
                ->with('edit_schedule_campaign_tab', 'keywords')
                ->with('edit_schedule_campaign_keywords_tab', 'multi');
        }

        $orderedIds = $this->orderedScheduleCampaignArticleIds($campaign);
        if (count($orderedIds) === 0) {
            return redirect()
                ->route('admin.schedule.campaign.edit', $campaign->id)
                ->with('cus__error', 'No campaign articles found.')
                ->with('edit_schedule_campaign_tab', 'keywords')
                ->with('edit_schedule_campaign_keywords_tab', 'multi');
        }

        if (count($keywords) !== count($orderedIds)) {
            return redirect()
                ->route('admin.schedule.campaign.edit', $campaign->id)
                ->with(
                    'cus__error',
                    'Keyword rows must be exactly '.count($orderedIds).' (your campaign post count).'
                )
                ->with('edit_schedule_campaign_tab', 'keywords')
                ->with('edit_schedule_campaign_keywords_tab', 'multi');
        }

        $postIdsToUpdateOnRemote = [];

        try {
            DB::transaction(function () use ($campaign, $keywords, $orderedIds, &$postIdsToUpdateOnRemote) {
                foreach ($orderedIds as $i => $articleId) {
                    $ca = ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)->find($articleId);
                    if (! $ca) {
                        throw new \RuntimeException('Campaign article missing.');
                    }

                    $row = $keywords[$i] ?? [];
                    $res = $this->applyScheduleCampaignKeywordBatch(
                        $campaign,
                        $ca,
                        $row['keyword'] ?? null,
                        $row['url'] ?? null,
                        [$ca->id]
                    );

                    if (! empty($res['error'])) {
                        throw new \RuntimeException($res['error']);
                    }

                    if (! empty($res['changed'])) {
                        $postIdsToUpdateOnRemote = array_merge($postIdsToUpdateOnRemote, $res['post_ids']);
                    }

                    $mediaVal = isset($row['media']) ? trim((string) $row['media']) : '';
                    $mediaVal = $mediaVal === '' ? null : $mediaVal;
                    $nofollow = ! empty($row['nofollow']);

                    ScheduleCampaignArticle::where('id', $ca->id)->update([
                        'media' => $mediaVal,
                        'nofollow' => $nofollow,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.schedule.campaign.edit', $campaign->id)
                ->with('cus__error', $e->getMessage())
                ->with('edit_schedule_campaign_tab', 'keywords')
                ->with('edit_schedule_campaign_keywords_tab', 'multi');
        }

        $postIdsToUpdateOnRemote = array_values(array_unique($postIdsToUpdateOnRemote));

        if (count($postIdsToUpdateOnRemote) > 0) {
            BulkUpdateScheduleCampaignPostsJob::dispatch($postIdsToUpdateOnRemote)->onQueue('schedule_campaign_bulk_updates');
        }

        $msg = count($postIdsToUpdateOnRemote) > 0
            ? 'Keywords updated. '.count($postIdsToUpdateOnRemote).' post(s) queued to update on remote.'
            : 'Keywords and media saved. No published posts to update on remote.';

        return redirect()
            ->route('admin.schedule.campaign.edit', $campaign->id)
            ->with('cus__success', $msg)
            ->with('edit_schedule_campaign_tab', 'keywords')
            ->with('edit_schedule_campaign_keywords_tab', 'multi');
    }

    /**
     * Bulk update keyword/URL batches; updates DB and queues remote post updates.
     */
    public function bulkUpdate(Request $request, string $id)
    {
        $campaign = ScheduleCampaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        $representativeIds = $request->input('batch_representative_id', []);

        if (! is_array($representativeIds)) {
            return back()->with('cus__error', 'Invalid form data.');
        }
        $isBulkTextarea = $request->exists('bulk_urls') && $request->exists('bulk_keywords');
        if ($isBulkTextarea) {
            $expected = count($representativeIds);
            $batchUrls = $this->parseManualLinesStrict((string) $request->input('bulk_urls', ''), $expected);
            $batchKeywords = $this->parseManualLinesStrict((string) $request->input('bulk_keywords', ''), $expected);
            if ($batchUrls === null || $batchKeywords === null) {
                return redirect()
                    ->route('admin.schedule.campaign.edit', $campaign->id)
                    ->with('cus__error', 'Bulk URLs and Bulk Keywords must each have exactly '.$expected.' non-empty lines.')
                    ->withInput($request->only(['bulk_urls', 'bulk_keywords']))
                    ->with('edit_schedule_campaign_tab', 'keywords')
                    ->with('edit_schedule_campaign_keywords_tab', 'bulk');
            }
        } else {
            $batchKeywords = $request->input('batch_keyword', []);
            $batchUrls = $request->input('batch_url', []);
            $batchKeywords = is_array($batchKeywords) ? array_values($batchKeywords) : [];
            $batchUrls = is_array($batchUrls) ? array_values($batchUrls) : [];
        }

        $updatedBatches = 0;
        $postIdsToUpdateOnRemote = [];

        foreach ($representativeIds as $index => $repId) {
            $repId = (int) $repId;
            $representative = ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)->find($repId);
            if (! $representative) {
                continue;
            }

            $kwInput = $batchKeywords[$index] ?? null;
            $urlInput = $batchUrls[$index] ?? null;

            $res = $this->applyScheduleCampaignKeywordBatch(
                $campaign,
                $representative,
                $kwInput,
                $urlInput,
                $isBulkTextarea ? [$representative->id] : null
            );

            if (! empty($res['error'])) {
                return redirect()
                    ->route('admin.schedule.campaign.edit', $campaign->id)
                    ->with('cus__error', $res['error'])
                    ->with('edit_schedule_campaign_tab', 'keywords')
                    ->with('edit_schedule_campaign_keywords_tab', $isBulkTextarea ? 'bulk' : 'batch');
            }

            if (! empty($res['changed'])) {
                $updatedBatches++;
                $postIdsToUpdateOnRemote = array_merge($postIdsToUpdateOnRemote, $res['post_ids']);
            }
        }

        $postIdsToUpdateOnRemote = array_values(array_unique($postIdsToUpdateOnRemote));

        if ($updatedBatches > 0 && count($postIdsToUpdateOnRemote) > 0) {
            BulkUpdateScheduleCampaignPostsJob::dispatch($postIdsToUpdateOnRemote)->onQueue('schedule_campaign_bulk_updates');
        }

        if ($updatedBatches === 0) {
            $msg = 'No keyword/URL changes were made.';
        } elseif (count($postIdsToUpdateOnRemote) > 0) {
            $msg = 'Batches updated. '.count($postIdsToUpdateOnRemote).' post(s) queued to update on remote.';
        } else {
            $msg = 'Batches updated. No published posts to update on remote.';
        }

        return redirect()
            ->route('admin.schedule.campaign.edit', $campaign->id)
            ->with($updatedBatches > 0 ? 'cus__success' : 'cus__error', $msg)
            ->with('edit_schedule_campaign_tab', 'keywords')
            ->with('edit_schedule_campaign_keywords_tab', $isBulkTextarea ? 'bulk' : 'batch');
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
     * Delete campaign: queue job to remove remote posts and delete all local data.
     */
    public function destroy(string $id)
    {
        $campaign = ScheduleCampaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        DeleteScheduleCampaignJob::dispatch($campaign->id)->onQueue('schedule_campaign_deletions');

        return redirect()
            ->route('admin.schedule.campaign.index')
            ->with('cus__success', 'Campaign deletion queued.');
    }

    /**
     * Remove campaign data from this application only. Remote WordPress posts are not deleted.
     */
    public function purgeLocalOnly(string $id)
    {
        $campaign = ScheduleCampaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }
        $this->authorizeCampaignAccess($campaign);
        PurgeLocalCampaignDataService::purgeScheduleCampaign((int) $campaign->id);

        return redirect()
            ->route('admin.schedule.campaign.index')
            ->with(
                'cus__success',
                'Campaign removed from this dashboard only. Remote posts were not deleted.'
            );
    }

    /**
     * Remove multiple schedule campaigns from the database only (no remote API calls).
     */
    public function bulkPurgeLocal(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return back()->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, ScheduleCampaign::class);
        if ($allowed === []) {
            return back()->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        foreach ($allowed as $id) {
            PurgeLocalCampaignDataService::purgeScheduleCampaign($id);
        }

        $n = count($allowed);

        return redirect()
            ->route('admin.schedule.campaign.index')
            ->with('cus__success', $n.' campaign(s) removed from this dashboard only. Remote posts were not deleted.');
    }

    /**
     * Bulk retry all failed posts across selected schedule campaigns.
     */
    public function bulkRetryFailed(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return back()->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, ScheduleCampaign::class);
        if ($allowed === []) {
            return back()->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        BulkRetryScheduleCampaignPostsJob::dispatch($allowed);

        $n = count($allowed);

        return redirect()
            ->route('admin.schedule.campaign.index')
            ->with('cus__success', 'Bulk retry queued for '.$n.' schedule campaign(s). All failed posts will be retried in the background. Run the queue worker to process them.');
    }

    /**
     * Retry a single schedule campaign post (queued/failed/publishing). Resets and re-dispatches job.
     */
    public function retryPost(int $postId)
    {
        $post = ScheduleCampaignPost::with('campaign')->findOrFail($postId);

        $cacheKey = 'schedule_campaign_retry_post_'.$post->id;
        if (Cache::has($cacheKey)) {
            return back()->with('cus__error', 'Retry was used recently for this post. Please wait 3 minutes.');
        }

        if (! in_array($post->status, ['queued', 'failed', 'publishing'], true)) {
            return back()->with('cus__error', 'Only failed, queued or stuck publishing posts can be retried.');
        }

        Cache::put($cacheKey, true, now()->addMinutes(3));

        $post->update([
            'status' => 'queued',
            'last_error' => null,
            'next_retry_at' => null,
            'locked_at' => null,
            'lock_token' => null,
        ]);

        PublishScheduledCampaignPostJob::dispatch($post->id, (int) ($post->dispatch_generation ?? 0))->onQueue('scheduled_campaigns');

        return back()->with('cus__success', 'Post queued for retry.');
    }

    /**
     * Edit a single schedule campaign post (only for published posts with remote_id). Fetches content from remote.
     */
    public function editPost(int $postId)
    {
        $post = ScheduleCampaignPost::with(['campaign', 'campaignDomain.domain'])->findOrFail($postId);

        if (empty($post->remote_id)) {
            return back()->with('cus__error', 'Post has no remote ID; only published posts can be edited.');
        }

        $domain = $post->campaignDomain?->domain;
        if (! $domain || ! $domain->api_key) {
            return back()->with('cus__error', 'Domain or API key is missing for this post.');
        }

        $domainName = trim((string) $domain->name);
        if (! preg_match('~^https?://~i', $domainName)) {
            $domainName = 'https://'.$domainName;
        }
        $url = rtrim($domainName, '/').'/wp-json/external/v1/posts/'.$post->remote_id.'?api_key='.urlencode($domain->api_key);

        try {
            $response = Http::withoutVerifying()->timeout(40)->get($url);
        } catch (\Throwable $e) {
            return back()->with('cus__error', 'Failed to fetch post from remote: '.$e->getMessage());
        }

        if ($response->failed()) {
            return back()->with('cus__error', 'Failed to fetch post from remote.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            return back()->with('cus__error', 'Remote did not return valid data.');
        }

        $fetchedData = WordPressApiFetchedPost::normalizeForEditForm($data);

        $keywordPairs = [];
        $post->load('campaignArticle');
        $ca = $post->campaignArticle;
        if ($ca) {
            if (($ca->keyword_type ?? '') === 'json') {
                $kwDec = json_decode($ca->keyword, true);
                $urlDec = json_decode($ca->url, true);
                if (is_array($kwDec) && is_array($urlDec)) {
                    $n = min(count($kwDec), count($urlDec));
                    for ($i = 0; $i < $n; $i++) {
                        $keywordPairs[] = [
                            'keyword' => (string) ($kwDec[$i] ?? ''),
                            'url' => (string) ($urlDec[$i] ?? ''),
                        ];
                    }
                }
            } else {
                $keywordPairs[] = [
                    'keyword' => (string) ($ca->keyword ?? ''),
                    'url' => (string) ($ca->url ?? ''),
                ];
            }
        }
        if (count($keywordPairs) === 0) {
            $keywordPairs[] = ['keyword' => '', 'url' => ''];
        }

        return view('admin.campaigns.pbn-post.edit-schedule-campaign-post', [
            'campaignPost' => $post,
            'campaign' => $post->campaign,
            'fetchedData' => $fetchedData,
            'keywordPairs' => $keywordPairs,
        ]);
    }

    /**
     * Update keyword/URL for one schedule post (single → multiple links, etc.).
     */
    public function updatePostKeywords(Request $request, int $postId)
    {
        $post = ScheduleCampaignPost::with('campaign')->find($postId);
        if (! $post || ! $post->campaign) {
            return back()->with('cus__error', 'Post not found.');
        }

        $validated = $request->validate([
            'batch_keyword' => 'required|array|min:1',
            'batch_url' => 'required|array|min:1',
        ]);

        $ca = ScheduleCampaignArticle::where('schedule_campaign_id', $post->schedule_campaign_id)
            ->whereKey($post->schedule_campaign_article_id)
            ->first();
        if (! $ca) {
            return back()->with('cus__error', 'Campaign article not found.');
        }

        $kwInput = $validated['batch_keyword'];
        $urlInput = $validated['batch_url'];
        $newKwList = array_values(array_map(fn ($v) => trim((string) $v), $kwInput));
        $newUrlList = array_values(array_map(fn ($v) => trim((string) $v), $urlInput));
        $len = min(count($newKwList), count($newUrlList));
        $newPairs = [];
        for ($i = 0; $i < $len; $i++) {
            $newPairs[] = [$newKwList[$i] ?? '', $newUrlList[$i] ?? ''];
        }

        if ($msg = CampaignKeywordPairValidator::validateEditPairs($newPairs)) {
            return back()->with('cus__error', $msg)->withInput();
        }

        $pairsToStore = array_values(array_filter(
            $newPairs,
            fn ($p) => trim((string) ($p[0] ?? '')) !== '' && trim((string) ($p[1] ?? '')) !== ''
        ));
        $newIsJson = count($pairsToStore) > 1;

        if (count($pairsToStore) === 0) {
            $kwStore = null;
            $urlStore = null;
        } elseif (count($pairsToStore) === 1 && ! $newIsJson) {
            $kwStore = $pairsToStore[0][0];
            $urlStore = $pairsToStore[0][1];
        } else {
            $kwStore = json_encode(array_column($pairsToStore, 0), JSON_UNESCAPED_UNICODE);
            $urlStore = json_encode(array_column($pairsToStore, 1), JSON_UNESCAPED_UNICODE);
        }

        $newKeywordType = $newIsJson ? 'json' : 'single';
        $newUrlType = $newIsJson ? 'json' : 'single';

        if ($ca->keyword === $kwStore && $ca->url === $urlStore
            && ($ca->keyword_type ?? 'single') === $newKeywordType
            && ($ca->url_type ?? 'single') === $newUrlType) {
            return back()->with('cus__error', 'No keyword/URL changes were made.');
        }

        ScheduleCampaignArticle::whereKey($ca->id)->update([
            'keyword' => $kwStore,
            'url' => $urlStore,
            'keyword_type' => $newKeywordType,
            'url_type' => $newUrlType,
        ]);

        $queued = $post->status === 'success' && ! empty($post->remote_id);
        if ($queued) {
            BulkUpdateScheduleCampaignPostsJob::dispatch([$post->id])->onQueue('schedule_campaign_bulk_updates');
        }

        $msg = $queued
            ? 'Keywords/URLs saved. Remote update queued. Run: php artisan queue:work --queue=schedule_campaign_bulk_updates'
            : 'Keywords/URLs saved. No published post to sync on remote.';

        return redirect()
            ->route('admin.schedule.campaign.edit.post', $post->id)
            ->with('cus__success', $msg);
    }

    /**
     * Update a single schedule campaign post on the remote site.
     */
    public function updatePost(Request $request, int $postId)
    {
        $post = ScheduleCampaignPost::with(['campaign', 'campaignDomain.domain'])->find($postId);
        if (! $post) {
            return back()->with('cus__error', 'The requested post is invalid.');
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
        ]);

        $domain = $post->campaignDomain?->domain;
        if (! $domain || ! $domain->api_key || empty($post->remote_id)) {
            return back()->with('cus__error', 'Domain, API key or remote ID is missing for this post.');
        }

        $domainName = trim((string) $domain->name);
        if (! preg_match('~^https?://~i', $domainName)) {
            $domainName = 'https://'.$domainName;
        }
        $url = rtrim($domainName, '/').'/wp-json/external/v1/posts/update/'.$post->remote_id.'?api_key='.urlencode($domain->api_key);

        try {
            $response = Http::withoutVerifying()
                ->timeout(120)
                ->asJson()
                ->post($url, [
                    'title' => $validated['name'],
                    'content' => $validated['description'],
                ]);
        } catch (\Throwable $e) {
            return back()->with('cus__error', 'Failed to update the post on the remote site: '.$e->getMessage());
        }

        if ($response->failed()) {
            return back()->with('cus__error', 'Failed to update the post on the remote site.');
        }

        $post->update([
            'remote_response' => $response->json() ?? $post->remote_response,
            'published_at' => $post->published_at ?? now(),
        ]);

        return redirect()
            ->route('admin.schedule.campaign.show', $post->schedule_campaign_id)
            ->with('cus__success', 'Post updated on the remote site.');
    }

    /**
     * Delete a single schedule campaign post (remote + DB). Unlocks article if queued. Decrements campaign counters.
     */
    public function deletePost(int $postId)
    {
        $post = ScheduleCampaignPost::with(['campaign', 'campaignArticle', 'campaignDomain.domain'])->find($postId);
        if (! $post) {
            return back()->with('cus__error', 'The requested post is invalid.');
        }

        $campaign = $post->campaign;
        $postStatus = $post->status;

        DB::transaction(function () use ($post, $campaign, $postStatus) {
            if ($postStatus === 'queued' && $post->campaignArticle) {
                $article = Article::find($post->campaignArticle->article_id);
                if ($article) {
                    $article->update(['lock_at' => null]);
                }
            }

            if ($campaign->total_targets > 0) {
                $campaign->decrement('total_targets');
            }
            if ($postStatus === 'success' && $campaign->completed_targets > 0) {
                $campaign->decrement('completed_targets');
            }
            if ($postStatus === 'failed' && $campaign->failed_targets > 0) {
                $campaign->decrement('failed_targets');
            }

            if ($postStatus === 'success' && $post->remote_id) {
                $domain = $post->campaignDomain?->domain;
                if ($domain && $domain->api_key) {
                    $domainName = trim((string) $domain->name);
                    if (! preg_match('~^https?://~i', $domainName)) {
                        $domainName = 'https://'.$domainName;
                    }
                    $url = rtrim($domainName, '/').'/wp-json/external/v1/posts/delete/'.$post->remote_id
                        .'?api_key='.urlencode($domain->api_key);
                    $response = Http::withoutVerifying()->timeout(60)->asJson()->delete($url);
                    if ($response->failed()) {
                        throw new \Exception('Remote delete failed: '.$response->body());
                    }
                }
            }

            $post->delete();
        });

        return back()->with('cus__success', 'Post deleted from the campaign and remote site.');
    }
}
