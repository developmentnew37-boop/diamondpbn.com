<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DomainCategory;
use Illuminate\Http\Request;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\ArticleSet;
use App\Models\Admin\DomainSet;
use App\Models\Admin\ScheduleCampaign;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\ScheduleCampaignDomain;
use App\Models\Admin\ScheduleCampaignArticle;
use App\Models\Admin\ScheduleCampaignPost;
use App\Models\Admin\ScheduleCampaignDate;
use App\Jobs\BulkUpdateScheduleCampaignPostsJob;
use App\Jobs\DeleteScheduleCampaignJob;
use App\Jobs\PublishScheduledCampaignPostJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\SimpleExcel\SimpleExcelWriter;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleLanguage;
use Illuminate\Support\Str;


class ScheduleCampaignController extends Controller
{
    public function __construct()
    {
        $this->middleware('can.create.campaigns')->except(['report', 'exportReport']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $limit = 100;

        $query = ScheduleCampaign::query()
            ->with([
                'domainCategory',
            ]);

        // 🔍 Search by campaign no
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
            'admin.campaigns.pbn-post.schedule-campaign',
            compact('campaigns', 'offset')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $campaignId = 'SCH-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        $articleCategory =  ArticleCategory::all();
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
        return view('admin.campaigns.pbn-post.create-schedule-campaign', compact('campaignId', 'domainCategory', 'articleCategory', 'articleSet', 'domainSets', 'articleLanguages'));
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
            $base = 'campaign-' . now()->timestamp;
        }
        $slug = $base;
        $counter = 1;
        do {
            $query = ScheduleCampaign::where('campaign_no', $slug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
            if (!$query->exists()) {
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
        $useDateTable = !empty($dateQuantitiesRaw);

        if ($useDateTable) {
            $dateRows = is_string($dateQuantitiesRaw) ? json_decode($dateQuantitiesRaw, true) : $dateQuantitiesRaw;
            if (!is_array($dateRows) || count($dateRows) === 0) {
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
            $scheduleTo   = $dateMax->format('Y-m-d');
        } else {
            $request->validate([
                'post_quantity'      => 'required|integer|min:1',
                'schedule_from_date' => 'required|date',
                'schedule_to_date'   => 'required|date|after_or_equal:schedule_from_date',
            ]);
            $postQty = (int) $request->post_quantity;
            $scheduleFrom = $request->schedule_from_date;
            $scheduleTo   = $request->schedule_to_date;
        }

        $validated = $request->validate([
            'campaign_no'           => 'required|string',
            'domain_category'       => 'nullable|integer|exists:domain_categories,id',
            'article_niche'         => 'nullable|integer|exists:article_categories,id',
            'sel_articles_opt'      => 'required|in:own_article,system_article,language_article',
            'selected_articles_val' => 'required|string',
            'keywordmethod'         => 'nullable|in:normal,bulk,multiple',
            'keywordsDataHolder'    => 'required|string',
            'campaigns_domains'     => 'required|string',
        ]);

        $articleIds = array_values(array_filter(
            array_map('intval', explode(',', $request->selected_articles_val))
        ));
        $domainIds = json_decode($request->campaigns_domains, true);
        if (!is_array($domainIds)) {
            return back()->with('cus__error', 'Invalid domains JSON')->withInput();
        }
        $domainIds = array_values(array_map('intval', $domainIds));
        $keywords = json_decode($request->keywordsDataHolder, true);
        if (!is_array($keywords)) {
            return back()->with('cus__error', 'Invalid keywords JSON')->withInput();
        }

        $method     = (string) ($request->keywordmethod ?? 'normal');
        $isMultiple = ($method === 'multiple');

        if (count($articleIds) !== $postQty || count($domainIds) !== $postQty || count($keywords) !== $postQty) {
            return back()
                ->with('cus__error', 'Articles, domains, and keywords count must equal total post quantity (' . $postQty . ').')
                ->withInput();
        }

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        if ($useDateTable) {
            $this->storeWithDateTable(
                $request,
                $campaignNo,
                $postQty,
                $scheduleFrom,
                $scheduleTo,
                $articleIds,
                $domainIds,
                $keywords,
                $dateRows,
                $isMultiple
            );
        } else {
            $from = Carbon::parse($scheduleFrom)->startOfDay();
            $to   = Carbon::parse($scheduleTo)->startOfDay();
            $totalDays = $from->diffInDays($to) + 1;
            $perDay    = intdiv($postQty, $totalDays);
            $remainder = $postQty % $totalDays;
            $this->storeWithDateRange(
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
                $isMultiple
            );
        }

        return redirect()
            ->route('admin.schedule.campaign.create')
            ->with('cus__success', 'Scheduled campaign created successfully.');
    }

    /**
     * Store campaign using per-date quantity table (date_quantities).
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
        bool $isMultiple
    ): void {
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
            $isMultiple
        ) {
            $campaign = ScheduleCampaign::create([
                'campaign_no'         => $campaignNo,
                'domain_category_id'  => $request->domain_category,
                'article_category_id' => $request->article_niche,
                'admin_id'            => auth('admin')->id(),
                'schedule_from_date'  => $scheduleFrom,
                'schedule_to_date'    => $scheduleTo,
                'status'              => 'queued',
                'total_targets'       => $postQty,
                'completed_targets'   => 0,
                'failed_targets'      => 0,
            ]);

            foreach ($domainIds as $i => $domainId) {
                ScheduleCampaignDomain::create([
                    'schedule_campaign_id' => $campaign->id,
                    'domain_id'            => $domainId,
                ]);
            }
            $domainMap = $campaign->domains()->orderBy('id')->pluck('id')->all();

            $articleMap = [];
            foreach ($articleIds as $i => $articleId) {
                $row    = $keywords[$i] ?? [];
                $kwVal  = $row['keyword'] ?? null;
                $urlVal = $row['url'] ?? null;
                if ($isMultiple) {
                    $kwArr  = is_array($kwVal)  ? $kwVal  : (is_null($kwVal)  ? [] : [$kwVal]);
                    $urlArr = is_array($urlVal) ? $urlVal : (is_null($urlVal) ? [] : [$urlVal]);
                    $kwArr  = array_values(array_filter(array_map(fn($v) => trim((string)$v), $kwArr)));
                    $urlArr = array_values(array_filter(array_map(fn($v) => trim((string)$v), $urlArr)));
                    $kwStore  = json_encode($kwArr, JSON_UNESCAPED_UNICODE);
                    $urlStore = json_encode($urlArr, JSON_UNESCAPED_UNICODE);
                    $kwType = 'json';
                    $urlType = 'json';
                } else {
                    $kwStore  = is_array($kwVal)  ? ($kwVal[0]  ?? null) : $kwVal;
                    $urlStore = is_array($urlVal) ? ($urlVal[0] ?? null) : $urlVal;
                    $kwStore  = $kwStore !== null ? trim((string)$kwStore) : null;
                    $urlStore = $urlStore !== null ? trim((string)$urlStore) : null;
                    if ($kwStore === '') $kwStore = null;
                    if ($urlStore === '') $urlStore = null;
                    $kwType = 'single';
                    $urlType = 'single';
                }
                $sca = ScheduleCampaignArticle::create([
                    'schedule_campaign_id' => $campaign->id,
                    'article_id'           => $articleId,
                    'keyword'              => $kwStore,
                    'url'                  => $urlStore,
                    'keyword_type'         => $kwType,
                    'url_type'             => $urlType,
                    'media'                => $row['media'] ?? null,
                    'nofollow'             => !empty($row['nofollow']),
                ]);
                $articleMap[$i] = $sca->id;
                $affected = Article::where('id', $articleId)->whereNull('lock_at')->update(['lock_at' => now()]);
                if ($affected === 0) {
                    throw new \Exception("Article {$articleId} is already locked.");
                }
            }

            foreach ($dateRows as $dr) {
                $q = (int) ($dr['quantity'] ?? 0);
                $d = $dr['date'] ?? null;
                if ($q < 1 || !$d) {
                    continue;
                }
                ScheduleCampaignDate::create([
                    'schedule_campaign_id' => $campaign->id,
                    'schedule_date'        => $d,
                    'quantity'             => $q,
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
                        'schedule_campaign_id'         => $campaign->id,
                        'schedule_campaign_domain_id'  => $domainMap[$globalIndex],
                        'schedule_campaign_article_id' => $articleMap[$globalIndex],
                        'schedule_at'                  => $scheduleAt,
                        'status'                       => 'queued',
                        'attempt_count'                => 0,
                        'created_at'                   => $now,
                        'updated_at'                   => $now,
                    ];
                    $globalIndex++;
                }
            }
            foreach (array_chunk($postRows, 200) as $chunk) {
                ScheduleCampaignPost::insert($chunk);
            }
        });
    }

    /**
     * Store campaign using from/to date range and even distribution (legacy).
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
        bool $isMultiple
    ): void {
        DB::transaction(function () use (
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
            $isMultiple
        ) {
            $campaign = ScheduleCampaign::create([
                'campaign_no'         => $campaignNo,
                'domain_category_id'  => $request->domain_category,
                'article_category_id' => $request->article_niche,
                'admin_id'            => auth('admin')->id(),
                'schedule_from_date'  => $request->schedule_from_date,
                'schedule_to_date'    => $request->schedule_to_date,
                'status'              => 'queued',
                'total_targets'       => $postQty,
                'completed_targets'   => 0,
                'failed_targets'      => 0,
            ]);

            $domainMap = [];
            foreach ($domainIds as $i => $domainId) {
                $row = ScheduleCampaignDomain::create([
                    'schedule_campaign_id' => $campaign->id,
                    'domain_id'            => $domainId,
                ]);
                $domainMap[$i] = $row->id;
            }

            $articleMap = [];
            foreach ($articleIds as $i => $articleId) {
                $row    = $keywords[$i] ?? [];
                $kwVal  = $row['keyword'] ?? null;
                $urlVal = $row['url'] ?? null;
                if ($isMultiple) {
                    $kwArr  = is_array($kwVal)  ? $kwVal  : (is_null($kwVal)  ? [] : [$kwVal]);
                    $urlArr = is_array($urlVal) ? $urlVal : (is_null($urlVal) ? [] : [$urlVal]);
                    $kwArr  = array_values(array_filter(array_map(fn($v) => trim((string)$v), $kwArr)));
                    $urlArr = array_values(array_filter(array_map(fn($v) => trim((string)$v), $urlArr)));
                    $kwStore  = json_encode($kwArr, JSON_UNESCAPED_UNICODE);
                    $urlStore = json_encode($urlArr, JSON_UNESCAPED_UNICODE);
                    $kwType  = 'json';
                    $urlType = 'json';
                } else {
                    $kwStore  = is_array($kwVal)  ? ($kwVal[0]  ?? null) : $kwVal;
                    $urlStore = is_array($urlVal) ? ($urlVal[0] ?? null) : $urlVal;
                    $kwStore  = $kwStore !== null ? trim((string)$kwStore) : null;
                    $urlStore = $urlStore !== null ? trim((string)$urlStore) : null;
                    if ($kwStore === '')  $kwStore = null;
                    if ($urlStore === '') $urlStore = null;
                    $kwType  = 'single';
                    $urlType = 'single';
                }
                $sca = ScheduleCampaignArticle::create([
                    'schedule_campaign_id' => $campaign->id,
                    'article_id'           => $articleId,
                    'keyword'              => $kwStore,
                    'url'                  => $urlStore,
                    'keyword_type'         => $kwType,
                    'url_type'             => $urlType,
                    'media'                => $row['media'] ?? null,
                    'nofollow'             => !empty($row['nofollow']),
                ]);
                $articleMap[$i] = $sca->id;
                $affected = Article::where('id', $articleId)->whereNull('lock_at')->update(['lock_at' => now()]);
                if ($affected === 0) {
                    throw new \Exception("Article {$articleId} is already locked and cannot be reused.");
                }
            }

            $rows = [];
            $now  = now();
            for ($i = 0; $i < $postQty; $i++) {
                if ($i < ($perDay + 1) * $remainder) {
                    $dayIndex = intdiv($i, $perDay + 1);
                } else {
                    $dayIndex = $remainder + intdiv($i - ($perDay + 1) * $remainder, $perDay);
                }
                $scheduleAt = $from->copy()->addDays($dayIndex)->setTime(0, 0);
                $rows[] = [
                    'schedule_campaign_id'         => $campaign->id,
                    'schedule_campaign_domain_id'  => $domainMap[$i],
                    'schedule_campaign_article_id' => $articleMap[$i],
                    'schedule_at'                  => $scheduleAt,
                    'status'                       => 'queued',
                    'attempt_count'                => 0,
                    'created_at'                   => $now,
                    'updated_at'                   => $now,
                ];
                if (count($rows) === 200) {
                    ScheduleCampaignPost::insert($rows);
                    $rows = [];
                }
            }
            if (!empty($rows)) {
                ScheduleCampaignPost::insert($rows);
            }
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // 🔐 Fetch scheduled campaign
        $campaign = ScheduleCampaign::findOrFail($id);

        $limit = 100;

        // 📦 Fetch scheduled posts
        $campaignPost = ScheduleCampaignPost::with([
            'campaignDomain.domain',
            'campaignArticle.article',
        ])
            ->where('schedule_campaign_id', $campaign->id)
            ->paginate($limit)
            ->withQueryString();

        // ->orderByDesc('id')

        $offset = ($campaignPost->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-post.view-schedule-campaign',
            compact('campaign', 'campaignPost', 'offset')
        );
    }
    /**
     * Show the form for editing the specified resource.
     */

    public function report(string $campaign_no, string $token)
    {
        // 🔐 1️⃣ Validate schedule campaign via token
        $campaign = ScheduleCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

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
            'campaignArticle'
        ])
            ->where('schedule_campaign_id', $campaign->id)
            ->get();

        // 🔎 4️⃣ Detect keyword type
        $keywordType = optional(
            $posts->first()?->campaignArticle
        )->keyword_type ?? 'single';

        // 🔢 5️⃣ Detect max keyword count (only for json)
        $maxKeywordCount = 1;

        if ($keywordType === 'json') {
            $maxKeywordCount = $posts->map(function ($post) {
                $ca = $post->campaignArticle;
                $keywords = json_decode($ca->keyword ?? '[]', true) ?? [];
                return count($keywords);
            })->max() ?? 1;
        }

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
            ->orderBy('id')
            ->get();

        /* =========================================================
       1️⃣ DETECT MAX KEYWORD / URL PAIRS
       ========================================================= */
        $maxPairs = 0;

        foreach ($posts as $post) {
            $ca = $post->campaignArticle;

            if (!$ca) {
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
                'S.No'         => $sno++,
                'Domain'       => optional($post->campaignDomain?->domain)->name ?? '-',
                'BlogPost'     => $post->remote_url ?? '-',
                'Scheduled At' => optional($post->schedule_at)?->format('d M Y H:i') ?? '-',
            ];

            $ca = $post->campaignArticle;

            if ($ca && $ca->keyword_type === 'json') {
                $keywords = json_decode($ca->keyword ?? '[]', true) ?? [];
                $urls     = json_decode($ca->url ?? '[]', true) ?? [];
            } else {
                $keywords = [$ca->keyword ?? ''];
                $urls     = [$ca->url ?? ''];
            }

            for ($i = 0; $i < $maxPairs; $i++) {
                if ($i === 0) {
                    $row['Keyword'] = $keywords[0] ?? '';
                    $row['URL']     = $urls[0] ?? '';
                } else {
                    $row["Keyword " . ($i + 1)] = $keywords[$i] ?? '';
                    $row["URL " . ($i + 1)]     = $urls[$i] ?? '';
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
        foreach ($articles as $ca) {
            $k = $ca->keyword === null ? '' : (string) $ca->keyword;
            $u = $ca->url === null ? '' : (string) $ca->url;
            $key = $k . "\n" . $u;

            if (!isset($batches[$key])) {
                $batches[$key] = [
                    'keyword'           => $k,
                    'url'               => $u,
                    'keyword_type'      => $ca->keyword_type ?? 'single',
                    'url_type'          => $ca->url_type ?? 'single',
                    'count'             => 0,
                    'article_ids'       => [],
                    'post_ids'          => [],
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

        return view('admin.campaigns.pbn-post.edit-schedule-campaign', compact('campaign', 'distinctBatches'));
    }

    /**
     * Update campaign no.
     */
    public function update(Request $request, string $id)
    {
        $campaign = ScheduleCampaign::find($id);
        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        $validated = $request->validate(['campaign_no' => 'required|string|max:191']);

        $campaignNo = $this->generateUniqueCampaignNo($validated['campaign_no'], (int) $campaign->id);
        $campaign->update(['campaign_no' => $campaignNo]);

        return back()->with('cus__success', 'Campaign updated.');
    }

    /**
     * Bulk update keyword/URL batches; updates DB and queues remote post updates.
     */
    public function bulkUpdate(Request $request, string $id)
    {
        $campaign = ScheduleCampaign::find($id);
        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        $representativeIds = $request->input('batch_representative_id', []);
        $batchKeywords     = $request->input('batch_keyword', []);
        $batchUrls         = $request->input('batch_url', []);

        if (!is_array($representativeIds)) {
            return back()->with('cus__error', 'Invalid form data.');
        }
        $batchKeywords = is_array($batchKeywords) ? array_values($batchKeywords) : [];
        $batchUrls     = is_array($batchUrls) ? array_values($batchUrls) : [];

        $updatedBatches = 0;
        $postIdsToUpdateOnRemote = [];

        foreach ($representativeIds as $index => $repId) {
            $repId = (int) $repId;
            $representative = ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)->find($repId);
            if (!$representative) {
                continue;
            }

            $kwInput  = $batchKeywords[$index] ?? null;
            $urlInput = $batchUrls[$index] ?? null;

            if (is_array($kwInput) && is_array($urlInput)) {
                $newKwList  = array_values(array_map(fn ($v) => trim((string) $v), $kwInput));
                $newUrlList = array_values(array_map(fn ($v) => trim((string) $v), $urlInput));
                $len = min(count($newKwList), count($newUrlList));
                $newPairs = [];
                for ($i = 0; $i < $len; $i++) {
                    $newPairs[] = [$newKwList[$i] ?? '', $newUrlList[$i] ?? ''];
                }
            } else {
                $newKw  = trim((string) $kwInput);
                $newUrl = trim((string) $urlInput);
                $newPairs = ($newKw !== '' || $newUrl !== '') ? [[$newKw, $newUrl]] : [];
            }

            $pairsToStore = array_values(array_filter(
                $newPairs,
                fn ($p) => ($p[0] ?? '') !== '' || ($p[1] ?? '') !== ''
            ));
            $newIsJson = count($pairsToStore) > 1;

            if (count($pairsToStore) === 0) {
                $kwStore = null;
                $urlStore = null;
            } elseif (count($pairsToStore) === 1 && !$newIsJson) {
                $kwStore  = $pairsToStore[0][0];
                $urlStore = $pairsToStore[0][1];
            } else {
                $kwStore  = json_encode(array_column($pairsToStore, 0), JSON_UNESCAPED_UNICODE);
                $urlStore = json_encode(array_column($pairsToStore, 1), JSON_UNESCAPED_UNICODE);
            }
            $newKeywordType = $newIsJson ? 'json' : 'single';
            $newUrlType     = $newIsJson ? 'json' : 'single';

            if ($representative->keyword === $kwStore && $representative->url === $urlStore
                && ($representative->keyword_type ?? 'single') === $newKeywordType
                && ($representative->url_type ?? 'single') === $newUrlType) {
                continue;
            }

            $articleIds = ScheduleCampaignArticle::where('schedule_campaign_id', $campaign->id)
                ->where('keyword', $representative->keyword)
                ->where('url', $representative->url)
                ->pluck('id')
                ->all();

            if (count($articleIds) === 0) {
                continue;
            }

            ScheduleCampaignArticle::whereIn('id', $articleIds)->update([
                'keyword'      => $kwStore,
                'url'          => $urlStore,
                'keyword_type' => $newKeywordType,
                'url_type'     => $newUrlType,
            ]);
            $updatedBatches++;

            $ids = ScheduleCampaignPost::whereIn('schedule_campaign_article_id', $articleIds)
                ->where('status', 'success')
                ->whereNotNull('remote_id')
                ->pluck('id')
                ->all();
            $postIdsToUpdateOnRemote = array_merge($postIdsToUpdateOnRemote, $ids);
        }

        $postIdsToUpdateOnRemote = array_values(array_unique($postIdsToUpdateOnRemote));

        if ($updatedBatches > 0 && count($postIdsToUpdateOnRemote) > 0) {
            BulkUpdateScheduleCampaignPostsJob::dispatch($postIdsToUpdateOnRemote)->onQueue('schedule_campaign_bulk_updates');
        }

        if ($updatedBatches === 0) {
            $msg = 'No keyword/URL changes were made.';
        } elseif (count($postIdsToUpdateOnRemote) > 0) {
            $msg = 'Batches updated. ' . count($postIdsToUpdateOnRemote) . ' post(s) queued to update on remote.';
        } else {
            $msg = 'Batches updated. No published posts to update on remote.';
        }

        return redirect()
            ->route('admin.schedule.campaign.edit', $campaign->id)
            ->with($updatedBatches > 0 ? 'cus__success' : 'cus__error', $msg);
    }

    /**
     * Delete campaign: queue job to remove remote posts and delete all local data.
     */
    public function destroy(string $id)
    {
        $campaign = ScheduleCampaign::find($id);
        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        DeleteScheduleCampaignJob::dispatch($campaign->id)->onQueue('schedule_campaign_deletions');

        return redirect()
            ->route('admin.schedule.campaign.index')
            ->with('cus__success', 'Campaign deletion queued.');
    }

    /**
     * Retry a single schedule campaign post (queued/failed/publishing). Resets and re-dispatches job.
     */
    public function retryPost(int $postId)
    {
        $post = ScheduleCampaignPost::with('campaign')->findOrFail($postId);

        $cacheKey = 'schedule_campaign_retry_post_' . $post->id;
        if (Cache::has($cacheKey)) {
            return back()->with('cus__error', 'Retry was used recently for this post. Please wait 3 minutes.');
        }

        if (!in_array($post->status, ['queued', 'failed', 'publishing'], true)) {
            return back()->with('cus__error', 'Only failed, queued or stuck publishing posts can be retried.');
        }

        Cache::put($cacheKey, true, now()->addMinutes(3));

        $post->update([
            'status'       => 'queued',
            'last_error'   => null,
            'next_retry_at' => null,
            'locked_at'    => null,
            'lock_token'   => null,
        ]);

        PublishScheduledCampaignPostJob::dispatch($post->id)->onQueue('scheduled_campaigns');

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
        if (!$domain || !$domain->api_key) {
            return back()->with('cus__error', 'Domain or API key is missing for this post.');
        }

        $domainName = trim((string) $domain->name);
        if (!preg_match('~^https?://~i', $domainName)) {
            $domainName = 'https://' . $domainName;
        }
        $url = rtrim($domainName, '/') . '/wp-json/external/v1/posts/' . $post->remote_id . '?api_key=' . urlencode($domain->api_key);

        try {
            $response = Http::withoutVerifying()->timeout(40)->get($url);
        } catch (\Throwable $e) {
            return back()->with('cus__error', 'Failed to fetch post from remote: ' . $e->getMessage());
        }

        if ($response->failed()) {
            return back()->with('cus__error', 'Failed to fetch post from remote.');
        }

        $data = $response->json();
        if (!is_array($data)) {
            return back()->with('cus__error', 'Remote did not return valid data.');
        }

        $fetchedData = [
            'post_title'   => (string) ($data['post_title'] ?? $data['title'] ?? $data['title']['rendered'] ?? ''),
            'post_content' => (string) ($data['post_content'] ?? $data['content'] ?? $data['content']['rendered'] ?? ''),
        ];
        if ($fetchedData['post_content'] === '' && isset($data['content']['raw'])) {
            $fetchedData['post_content'] = (string) $data['content']['raw'];
        }

        return view('admin.campaigns.pbn-post.edit-schedule-campaign-post', [
            'campaignPost' => $post,
            'campaign'    => $post->campaign,
            'fetchedData' => $fetchedData,
        ]);
    }

    /**
     * Update a single schedule campaign post on the remote site.
     */
    public function updatePost(Request $request, int $postId)
    {
        $post = ScheduleCampaignPost::with(['campaign', 'campaignDomain.domain'])->find($postId);
        if (!$post) {
            return back()->with('cus__error', 'The requested post is invalid.');
        }

        $validated = $request->validate([
            'name'        => 'required|string',
            'description' => 'required|string',
        ]);

        $domain = $post->campaignDomain?->domain;
        if (!$domain || !$domain->api_key || empty($post->remote_id)) {
            return back()->with('cus__error', 'Domain, API key or remote ID is missing for this post.');
        }

        $domainName = trim((string) $domain->name);
        if (!preg_match('~^https?://~i', $domainName)) {
            $domainName = 'https://' . $domainName;
        }
        $url = rtrim($domainName, '/') . '/wp-json/external/v1/posts/update/' . $post->remote_id . '?api_key=' . urlencode($domain->api_key);

        try {
            $response = Http::withoutVerifying()
                ->timeout(120)
                ->asJson()
                ->post($url, [
                    'title'   => $validated['name'],
                    'content' => $validated['description'],
                ]);
        } catch (\Throwable $e) {
            return back()->with('cus__error', 'Failed to update the post on the remote site: ' . $e->getMessage());
        }

        if ($response->failed()) {
            return back()->with('cus__error', 'Failed to update the post on the remote site.');
        }

        $post->update([
            'remote_response' => $response->json() ?? $post->remote_response,
            'published_at'    => $post->published_at ?? now(),
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
        if (!$post) {
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
                    if (!preg_match('~^https?://~i', $domainName)) {
                        $domainName = 'https://' . $domainName;
                    }
                    $url = rtrim($domainName, '/') . '/wp-json/external/v1/posts/delete/' . $post->remote_id
                        . '?api_key=' . urlencode($domain->api_key);
                    $response = Http::withoutVerifying()->timeout(60)->asJson()->delete($url);
                    if ($response->failed()) {
                        throw new \Exception('Remote delete failed: ' . $response->body());
                    }
                }
            }

            $post->delete();
        });

        return back()->with('cus__success', 'Post deleted from the campaign and remote site.');
    }
}
