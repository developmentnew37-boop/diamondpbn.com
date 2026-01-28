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
use Illuminate\Support\Carbon;
use Spatie\SimpleExcel\SimpleExcelWriter;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleLanguage;
use Illuminate\Support\Str;


class ScheduleCampaignController extends Controller
{
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
        while (ScheduleCampaign::where('campaign_no', $slug)->exists()) {
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
            'campaign_no'           => 'required|string',
            'domain_category'       => 'nullable|integer|exists:domain_categories,id',
            'post_quantity'         => 'required|integer|min:1',

            'schedule_from_date'    => 'required|date',
            'schedule_to_date'      => 'required|date|after_or_equal:schedule_from_date',

            'article_niche'         => 'nullable|integer|exists:article_categories,id',
            'sel_articles_opt'      => 'required|in:own_article,system_article,language_article',
            'selected_articles_val' => 'required|string', // CSV
            'keywordmethod'         => 'nullable|in:normal,bulk,multiple',
            'keywordsDataHolder'    => 'required|string', // JSON
            'campaigns_domains'     => 'required|string', // JSON
        ]);

        // =====================================================
        // 2️⃣ NORMALIZE INPUT
        // =====================================================
        $postQty = (int) $request->post_quantity;

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

        // keyword mode
        $method     = (string) ($request->keywordmethod ?? 'normal');
        $isMultiple = ($method === 'multiple');

        // =====================================================
        // 3️⃣ HARD COUNT CHECK
        // =====================================================
        if (
            count($articleIds) !== $postQty ||
            count($domainIds) !== $postQty ||
            count($keywords) !== $postQty
        ) {
            return back()
                ->with('cus__error', 'Articles, domains, and keywords must match post quantity')
                ->withInput();
        }

        // =====================================================
        // 4️⃣ DATE MATH
        // =====================================================
        $from = Carbon::parse($request->schedule_from_date)->startOfDay();
        $to   = Carbon::parse($request->schedule_to_date)->startOfDay();

        $totalDays = $from->diffInDays($to) + 1;
        $perDay    = intdiv($postQty, $totalDays);
        $remainder = $postQty % $totalDays;

        // updated campaign no

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        // =====================================================
        // 5️⃣ TRANSACTION
        // =====================================================
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

            // ---------------------------------------------
            // A) schedule_campaigns
            // ---------------------------------------------
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

            // ---------------------------------------------
            // B) schedule_campaigns_domains
            // ---------------------------------------------
            $domainMap = [];
            foreach ($domainIds as $i => $domainId) {
                $row = ScheduleCampaignDomain::create([
                    'schedule_campaign_id' => $campaign->id,
                    'domain_id'            => $domainId,
                ]);
                $domainMap[$i] = $row->id;
            }

            // ---------------------------------------------
            // C) schedule_campaigns_articles (🔥 FIXED)
            // ---------------------------------------------
            $articleMap = [];

            foreach ($articleIds as $i => $articleId) {

                $row    = $keywords[$i] ?? [];
                $kwVal  = $row['keyword'] ?? null; // string OR array
                $urlVal = $row['url'] ?? null;

                if ($isMultiple) {
                    // MULTIPLE → JSON
                    $kwArr  = is_array($kwVal)  ? $kwVal  : (is_null($kwVal)  ? [] : [$kwVal]);
                    $urlArr = is_array($urlVal) ? $urlVal : (is_null($urlVal) ? [] : [$urlVal]);

                    $kwArr  = array_values(array_filter(array_map(fn($v) => trim((string)$v), $kwArr)));
                    $urlArr = array_values(array_filter(array_map(fn($v) => trim((string)$v), $urlArr)));

                    $kwStore  = json_encode($kwArr, JSON_UNESCAPED_UNICODE);
                    $urlStore = json_encode($urlArr, JSON_UNESCAPED_UNICODE);

                    $kwType  = 'json';
                    $urlType = 'json';
                } else {
                    // SINGLE
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

                // 🔒 LOCK ARTICLE SAFELY
                $affected = Article::where('id', $articleId)
                    ->whereNull('lock_at')
                    ->update(['lock_at' => now()]);

                if ($affected === 0) {
                    throw new \Exception("Article {$articleId} is already locked and cannot be reused.");
                }
            }

            // ---------------------------------------------
            // D) schedule_campaigns_posts
            // ---------------------------------------------
            $rows = [];
            $now  = now();

            for ($i = 0; $i < $postQty; $i++) {

                if ($i < ($perDay + 1) * $remainder) {
                    $dayIndex = intdiv($i, $perDay + 1);
                } else {
                    $dayIndex = $remainder + intdiv(
                        $i - ($perDay + 1) * $remainder,
                        $perDay
                    );
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

        return redirect()
            ->route('admin.schedule.campaign.create')
            ->with('cus__success', 'Scheduled campaign created successfully.');
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
