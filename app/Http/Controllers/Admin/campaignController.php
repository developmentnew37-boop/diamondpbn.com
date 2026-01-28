<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\ArticleSet;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\CampaignArticle;
use App\Models\Admin\CampaignDomain;
use App\Models\Admin\CampaignPost;
use App\Models\Admin\Campaign;
use Illuminate\Support\Facades\DB;
use App\Jobs\PublishCampaignPostJob;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleLanguage;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Str;

class campaignController extends Controller
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

        // ✅ Clean empty search from URL
        if ($request->has('search') && trim($request->search) === '') {
            return redirect()->to(
                url()->current() . '?' . http_build_query(
                    $request->except('search')
                )
            );
        }

        // ✅ Pagination limit
        $limit = 100;
        $admin = Auth::guard('admin')->user();
        // ✅ Base query
        $query = Campaign::query()
            ->where('is_sticky_campaign', false);

        // 🔍 Search by campaign_no
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where('campaign_no', 'LIKE', $search);
        }

        if(!$admin->isSuperAdmin()){
            $query->where('admin_id', $admin->id);
        }

        // ✅ Paginate + keep query params
        $campaigns = $query
            ->orderByDesc('id')
            ->paginate($limit)
            ->appends($request->all());

        // ✅ Offset (for serial numbers in table)
        $offset = ($campaigns->currentPage() - 1) * $limit;

        return view('admin.campaigns.pbn-post.campaign', compact('campaigns', 'offset'));
    }



    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $campaignId = 'CMP-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        // // domain + article category
        $domainCategory = DomainCategory::all();
        $articleCategory =  ArticleCategory::all();
        // // ** now fetching the user the articles ** //
        // $articleSet = ArticleSet::withCount('articles')->where('admin_id', auth('admin')->id())->get();
        // $articleSet = ArticleSet::with('articles')->where('admin_id', auth('admin')->id())->get();
        $articleSet = ArticleSet::withCount([
            'articles' => function ($q) {
                $q->whereNot('status', 1);
            }
        ])
            ->where('admin_id', auth('admin')->id())
            ->get();
        // ** now providing user domain sets
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();
        // ** article languages with article count
        $articleLanguages = ArticleLanguage::withCount(['Article' => function ($query) {
            $query->where('status', 0)
                ->whereNull('deleted_at')
                ->whereNull('lock_at')
                ->where('status', '!=', '1');
        }])->having('article_count', '>', 0)->get();
        $is_sticky = 0;
        return view('admin.campaigns.pbn-post.create-campaign', compact('campaignId', 'domainCategory', 'articleCategory', 'articleSet', 'domainSets', 'articleLanguages', 'is_sticky'));
    }

    /**
     * Store a newly created resource in storage.
     */


    // ***** contains function to add the campaign data ********
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
        while (Campaign::where('campaign_no', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function store(Request $request)
    {

        // ✅ Validation (matches your REAL incoming formats)
        $validated = $request->validate([
            'campaign_no'           => 'required|string',
            'domain_category'       => 'nullable|integer|exists:domain_categories,id',
            'post_quantity'         => 'required|integer|min:1',

            'article_niche'         => 'nullable|integer|exists:article_categories,id',
            'sel_articles_opt'      =>  'required|in:own_article,system_article,language_article',
            'selected_articles_val' => 'required|string',   // CSV: "163,164,165,..."

            'keywordmethod'         => 'nullable|string|in:normal,bulk,multiple',
            'keywordsDataHolder'    => 'required|string',   // JSON string array

            'sel_domains'           => 'required|integer|in:0,1,2',
            'campaigns_domains'     => 'required|string',   // JSON: ["48","49",...]
            'is_sticky' => 'nullable|in:0,1',
        ]);

        // ✅ Normalize inputs
        $postQty = (int) $request->post_quantity;

        // Articles: CSV -> array[int]
        $articleIds = array_values(array_filter(array_map('trim', explode(',', (string) $request->selected_articles_val))));
        $articleIds = array_values(array_filter(array_map('intval', $articleIds))); // remove non-numeric

        // issticky
        $isSticky = filter_var(
            $request->input('is_sticky', false),
            FILTER_VALIDATE_BOOLEAN
        );

        // Domains: JSON -> array[int]
        $domainIds = json_decode((string) $request->campaigns_domains, true);
        if (!is_array($domainIds)) {
            return back()->with('cus__error', 'Domains data is invalid JSON.')->withInput();
        }
        $domainIds = array_values(array_filter(array_map('intval', $domainIds)));

        // Keywords: JSON -> array
        $keywords = json_decode((string) $request->keywordsDataHolder, true);
        if (!is_array($keywords)) {
            return back()->with('keywordsDataHolder', 'Keywords data is invalid JSON.')->withInput();
        }

        // ✅ Hard rule: all counts must match post_quantity
        if (count($articleIds) !== $postQty) {
            return back()->with('selected_articles_val', "You must select exactly {$postQty} articles.")->withInput();
        }
        if (count($domainIds) !== $postQty) {
            return back()->with('campaigns_domains', "You must select exactly {$postQty} domains.")->withInput();
        }
        if (count($keywords) !== $postQty) {
            return back()->with('keywordsDataHolder', "Keywords rows must be exactly {$postQty}.")->withInput();
        }

        // ✅ Map UI article option -> DB enum (IMPORTANT to avoid SQL error)
        // campaigns.article_type enum is: ['article_set','in_article']
        $articleType = $request->sel_articles_opt;

        // dd(["article_type" => $articleType]);

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        // ✅ Insert into 4 tables atomically
        $campaign = DB::transaction(function () use ($request, $campaignNo, $postQty, $articleIds, $domainIds, $keywords, $articleType, $isSticky) {

            // 1) campaigns
            $campaign = Campaign::create([
                'campaign_no'         => $campaignNo,
                'domain_category_id'  => $request->domain_category ?: null,
                'article_category_id' => $request->article_niche ?: null,
                'admin_id'            => auth('admin')->id(),
                'article_type'        => $articleType,
                'status'              => 'queued',
                'is_sticky_campaign'  => $isSticky,
                'total_targets'       => $postQty,
                'completed_targets'   => 0,
                'failed_targets'      => 0,
            ]);

            // 2) campaign_domains (store and keep map by index)
            $campaignDomainIds = [];
            foreach ($domainIds as $i => $domainId) {
                $cd = CampaignDomain::create([
                    'campaign_id' => $campaign->id,
                    'domain_id'   => $domainId,
                    'sort_order'  => $i + 1,
                ]);
                $campaignDomainIds[$i] = $cd->id;
            }

            // 3) campaign_articles (store and keep map by index)
            $campaignArticleIds = [];
            $method = (string) ($request->keywordmethod ?? 'normal'); // normal | bulk | multiple
            $isMultiple = ($method === 'multiple');

            foreach ($articleIds as $i => $articleId) {
                $row = $keywords[$i] ?? [];

                $kwVal  = $row['keyword'] ?? null; // string OR array
                $urlVal = $row['url'] ?? null;     // string OR array

                // ✅ Rule: keywordmethod decides types (not the incoming shape)
                $kwType  = $isMultiple ? 'json' : 'single';
                $urlType = $isMultiple ? 'json' : 'single';

                // ✅ Normalize storage exactly based on method
                if ($isMultiple) {
                    // Always store arrays as JSON. If single string comes, wrap into array.
                    $kwArr  = is_array($kwVal)  ? $kwVal  : (is_null($kwVal) ? [] : [$kwVal]);
                    $urlArr = is_array($urlVal) ? $urlVal : (is_null($urlVal) ? [] : [$urlVal]);

                    // Optional: trim + remove empty
                    $kwArr  = array_values(array_filter(array_map(fn($v) => trim((string)$v), $kwArr), fn($v) => $v !== ''));
                    $urlArr = array_values(array_filter(array_map(fn($v) => trim((string)$v), $urlArr), fn($v) => $v !== ''));

                    $kwStore  = json_encode($kwArr, JSON_UNESCAPED_UNICODE);
                    $urlStore = json_encode($urlArr, JSON_UNESCAPED_UNICODE);
                } else {
                    // Always store single string. If array comes, take first non-empty.
                    $kwStore = is_array($kwVal) ? ($kwVal[0] ?? null) : $kwVal;
                    $urlStore = is_array($urlVal) ? ($urlVal[0] ?? null) : $urlVal;

                    $kwStore  = is_null($kwStore)  ? null : trim((string)$kwStore);
                    $urlStore = is_null($urlStore) ? null : trim((string)$urlStore);

                    if ($kwStore === '') $kwStore = null;
                    if ($urlStore === '') $urlStore = null;
                }

                $ca = CampaignArticle::create([
                    'campaign_id'  => $campaign->id,
                    'article_id'   => (int) $articleId,
                    'keyword'      => $kwStore,
                    'url'          => $urlStore,
                    'media'        => $row['media'] ?? null,
                    'keyword_type' => $kwType,
                    'url_type'     => $urlType,
                    'nofollow' => !empty($row['nofollow']),
                ]);

                $campaignArticleIds[$i] = $ca->id;

                // article lock_at thing

                // 🔒 LOCK ARTICLE SAFELY
                $affected = Article::where('id', $articleId)
                    ->whereNull('lock_at')
                    ->update(['lock_at' => now(), 'status' => 1]);

                if ($affected === 0) {
                    throw new \Exception("Article {$articleId} is already locked and cannot be reused.");
                }
            }
            // 4) campaign_posts (1 domain row ↔ 1 article row)
            for ($i = 0; $i < $postQty; $i++) {
                CampaignPost::create([
                    'campaign_id'         => $campaign->id,
                    'campaign_domain_id'  => $campaignDomainIds[$i],
                    'campaign_article_id' => $campaignArticleIds[$i],
                    'status'              => 'queued',
                    'is_sticky' =>  $isSticky,
                    'attempt_count'       => 0,
                ]);
            }

            // ** dispatching the job **
            $postIds = CampaignPost::where('campaign_id', $campaign->id)
                ->pluck('id')
                ->all();

            DB::afterCommit(function () use ($postIds) {
                foreach ($postIds as $id) {
                    PublishCampaignPostJob::dispatch($id)->onQueue('campaigns');
                }
            });
            // ** ends here **

            return $campaign;
        });

        // return redirect()
        //     ->route('admin.campaign.create')
        //     ->with('cus__success', "Campaign {$campaign->campaign_no} created successfully ({$campaign->total_targets} posts).");

        $prefix = $isSticky ? 'Sticky ' : '';

        return back()->with(
            'cus__success',
            "{$prefix} Campaign {$campaign->campaign_no} created successfully ({$campaign->total_targets} posts)."
        );
    }
    // ****** Ends here ******

    /**
     * Display the specified resource.
     */
    // public function show(string $id)
    // {
    //     //
    //     $campaign = Campaign::find($id);

    //     $campaignPost = campaignPost::where('campaign_id', $id)->get();

    //     return view('admin.campaigns.pbn-post.view-campaign', compact('campaign','campaignPost'));
    // }

    public function show(Request $request, string $id)
    {
        // Fetch campaign (fail-safe)
        $campaign = Campaign::findOrFail($id);

        // Pagination limit
        $limit = 100;

        // Campaign posts query
        $campaignPost = CampaignPost::where('campaign_id', $id)
            ->paginate($limit)
            ->withQueryString();
        // ->orderByDesc('id')

        // Offset for S.No
        $offset = ($campaignPost->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-post.view-campaign',
            compact('campaign', 'campaignPost', 'offset')
        );
    }


    // public function report(string $campaign_no, string $token)
    // {
    //     // 1️⃣ Validate campaign via token
    //     $campaign = Campaign::where('campaign_no', $campaign_no)
    //         ->where('report_token', $token)
    //         ->firstOrFail();

    //     // 2️⃣ Aggregate stats
    //     $stats = CampaignPost::where('campaign_id', $campaign->id)
    //         ->selectRaw("
    //         COUNT(*) as total,
    //         SUM(status = 'success') as success,
    //         SUM(status IN ('queued','publishing')) as queued,
    //         SUM(status = 'failed') as failed
    //     ")
    //        ->first();

    //     $successRate = $stats->total > 0
    //         ? round(($stats->success / $stats->total) * 100)
    //         : 0;

    //     // 3️⃣ Paginated posts
    //     $limit = 25;

    //     $posts = CampaignPost::with([
    //         'campaignDomain.domain',
    //         'campaignArticle'
    //     ])
    //         ->where('campaign_id', $campaign->id)
    //         ->orderByDesc('id')
    //         ->paginate($limit)
    //         ->withQueryString();

    //     $offset = ($posts->currentPage() - 1) * $limit;

    //     // 🔑 4️⃣ Detect keyword type (single / json)
    //     $keywordType = optional(
    //         $posts->first()?->campaignArticle
    //     )->keyword_type ?? 'single';

    //     return view(
    //         'admin.campaigns.pbn-post.campaign-report',
    //         compact(
    //             'campaign',
    //             'stats',
    //             'successRate',
    //             'posts',
    //             'offset',
    //             'keywordType' // ✅ IMPORTANT
    //         )
    //     );
    // }

    public function report(string $campaign_no, string $token)
    {
        // 1️⃣ Validate campaign via token (PUBLIC & SECURE)
        $campaign = Campaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        // 2️⃣ Aggregate stats (single source of truth)
        $stats = CampaignPost::where('campaign_id', $campaign->id)
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

        // 3️⃣ Paginated posts

        // $posts = CampaignPost::with([
        //     'campaignDomain.domain',
        //     'campaignArticle'
        // ])
        //     ->where('campaign_id', $campaign->id)
        //     ->get();

        $posts = CampaignPost::with([
            'campaignDomain.domain',
            'campaignArticle'
        ])
            ->join(
                'campaign_domains',
                'campaign_posts.campaign_domain_id',
                '=',
                'campaign_domains.id'
            )
            ->where('campaign_posts.campaign_id', $campaign->id) // ✅ FIX
            ->orderBy('campaign_domains.sort_order', 'asc')
            ->select('campaign_posts.*')
            ->get();




        // 4️⃣ Detect keyword type (single / json)
        $keywordType = optional(
            $posts->first()?->campaignArticle
        )->keyword_type ?? 'single';

        // 5️⃣ Detect max keyword count (ONLY for json)
        $maxKeywordCount = 1;

        if ($keywordType === 'json') {
            $maxKeywordCount = $posts->map(function ($post) {
                $ca = $post->campaignArticle;
                $keywords = json_decode($ca->keyword ?? '[]', true) ?? [];
                return count($keywords);
            })->max() ?? 1;
        }

        return view(
            'admin.campaigns.pbn-post.campaign-report',
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


    // public function exportReport(string $campaign_no, string $token)
    // {
    //     // 🔐 Validate campaign
    //     $campaign = Campaign::where('campaign_no', $campaign_no)
    //         ->where('report_token', $token)
    //         ->firstOrFail();

    //     // 📦 Fetch posts
    //     $posts = CampaignPost::with([
    //         'campaignDomain.domain',
    //         'campaignArticle'
    //     ])
    //         ->where('campaign_id', $campaign->id)
    //         ->orderBy('id')
    //         ->get();

    //     /* =========================================================
    //    1️⃣  DETECT MAX KEYWORD / URL PAIRS (FIXED LOGIC)
    //        ========================================================= */
    //     $maxPairs = 0;

    //     foreach ($posts as $post) {
    //         $ca = $post->campaignArticle;

    //         if (!$ca) {
    //             continue;
    //         }

    //         if ($ca->keyword_type === 'json') {
    //             $count = count(json_decode($ca->keyword ?? '[]', true));
    //         } else {
    //             // ✅ single keyword must count as 1
    //             $count = ($ca->keyword || $ca->url) ? 1 : 0;
    //         }

    //         $maxPairs = max($maxPairs, $count);
    //     }

    //     // ✅ Always show at least one Keyword/URL column
    //     $maxPairs = max($maxPairs, 1);

    //     /* =========================================================
    //      2️⃣ BUILD HEADERS (STABLE STRUCTURE)
    //      ========================================================= */
    //     $headers = [
    //         'S.No',
    //         'Domain',
    //         'BlogPost',
    //     ];

    //     for ($i = 1; $i <= $maxPairs; $i++) {
    //         if ($i === 1) {
    //             $headers[] = 'Keyword';
    //             $headers[] = 'URL';
    //         } else {
    //             $headers[] = "Keyword {$i}";
    //             $headers[] = "URL {$i}";
    //         }
    //     }

    //     $headers[] = 'Status';
    //     $headers[] = 'Date';

    //     /* =========================================================
    //      3️⃣ CREATE EXCEL
    //        ========================================================= */
    //     $writer = SimpleExcelWriter::streamDownload(
    //         "campaign-report-{$campaign_no}.xlsx"
    //     )->addHeader($headers);

    //     /* =========================================================
    //      4️⃣ FILL ROWS (NO SKIPPING)
    //        ========================================================= */
    //     $sno = 1;

    //     foreach ($posts as $post) {

    //         $row = [
    //             'S.No'        => $sno++,
    //             'Domain'      => optional($post->campaignDomain?->domain)->name ?? '-',
    //             'BlogPost'    => $post->remote_url ?? '-',
    //         ];

    //         $ca = $post->campaignArticle;

    //         if ($ca && $ca->keyword_type === 'json') {
    //             $keywords = json_decode($ca->keyword ?? '[]', true) ?? [];
    //             $urls     = json_decode($ca->url ?? '[]', true) ?? [];
    //         } else {
    //             // ✅ single keyword normalized
    //             $keywords = [$ca->keyword ?? ''];
    //             $urls     = [$ca->url ?? ''];
    //         }

    //         // 🔹 Fill keyword/url columns safely
    //         for ($i = 0; $i < $maxPairs; $i++) {
    //             if ($i === 0) {
    //                 $row['Keyword'] = $keywords[0] ?? '';
    //                 $row['URL']     = $urls[0] ?? '';
    //             } else {
    //                 $row["Keyword " . ($i + 1)] = $keywords[$i] ?? '';
    //                 $row["URL " . ($i + 1)]     = $urls[$i] ?? '';
    //             }
    //         }

    //         // 🔹 Status + Date
    //         $row['Status'] = $post->status === 'success' ? 'Live' : 'Not Live';
    //         $row['Date']   = optional($post->created_at)->format('d M Y');

    //         $writer->addRow($row);
    //     }

    //     return $writer->toBrowser();
    // }

    // export to excel

    public function exportReport(string $campaign_no, string $token)
    {
        // 🔐 Validate campaign
        $campaign = Campaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        // 📦 Fetch posts
        $posts = CampaignPost::with([
            'campaignDomain.domain',
            'campaignArticle'
        ])
            ->where('campaign_id', $campaign->id)
            ->get();
        // ->orderBy('id')

        /* =========================================================
       1️⃣ DETECT IF ANY STICKY POST EXISTS
       ========================================================= */
        // $hasStickyPost = $posts->contains(function ($post) {
        //     return !empty($post->is_sticky_campaign) && $post->is_sticky_campaign;
        // });
        $hasStickyPost = $campaign->is_sticky_campaign ? true : false;

        /* =========================================================
       2️⃣ DETECT MAX KEYWORD / URL PAIRS (UNCHANGED)
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
       3️⃣ BUILD HEADERS (ONLY ADD POST IF STICKY EXISTS)
       ========================================================= */
        $headers = [
            'S.No',
            'Domain',
            'BlogPost',
        ];

        if ($hasStickyPost) {
            $headers[] = 'Post';
        }

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
        $headers[] = 'Date';

        /* =========================================================
       4️⃣ CREATE EXCEL
       ========================================================= */
        $writer = SimpleExcelWriter::streamDownload(
            "campaign-report-{$campaign_no}.xlsx"
        )->addHeader($headers);

        /* =========================================================
       5️⃣ FILL ROWS (POST COLUMN CONDITIONAL)
       ========================================================= */
        $sno = 1;

        foreach ($posts as $post) {

            $row = [
                'S.No'     => $sno++,
                'Domain'   => optional($post->campaignDomain?->domain)->name ?? '-',
                'BlogPost' => $post->remote_url ?? '-',
            ];

            if ($hasStickyPost) {
                $row['Post'] = (!empty($post->is_sticky) && $post->is_sticky)
                    ? 'Sticky'
                    : '';
            }

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

            $row['Status'] = $post->status === 'success' ? 'Live' : 'Not Live';
            $row['Date']   = optional($post->created_at)->format('d M Y');

            $writer->addRow($row);
        }

        return $writer->toBrowser();
    }


    

    // export to csv

    // public function exportReportCsv(string $campaign_no, string $token)
    // {
    //     // 🔐 Validate campaign
    //     $campaign = Campaign::where('campaign_no', $campaign_no)
    //         ->where('report_token', $token)
    //         ->firstOrFail();

    //     $posts = CampaignPost::with([
    //         'campaignDomain.domain',
    //         'campaignArticle'
    //     ])
    //         ->where('campaign_id', $campaign->id)
    //         ->orderBy('id')
    //         ->get();

    //     /* =========================================================
    //        1️⃣ DETECT MAX KEYWORD / URL PAIRS (FIXED)
    //        ========================================================= */
    //     $maxPairs = 0;

    //     foreach ($posts as $post) {
    //         $ca = $post->campaignArticle;

    //         if (!$ca) {
    //             continue;
    //         }

    //         if ($ca->keyword_type === 'json') {
    //             $count = count(json_decode($ca->keyword ?? '[]', true));
    //         } else {
    //             // ✅ single keyword must count as 1
    //             $count = ($ca->keyword || $ca->url) ? 1 : 0;
    //         }

    //         $maxPairs = max($maxPairs, $count);
    //     }

    //     // ✅ Always at least one Keyword / URL column
    //     $maxPairs = max($maxPairs, 1);

    //     $fileName = 'campaign-report-' . $campaign->campaign_no . '.csv';

    //     return response()->streamDownload(function () use ($posts, $campaign, $maxPairs) {

    //         $writer = SimpleExcelWriter::create('php://output', 'csv');

    //         /* =========================================================
    //        2️⃣ BUILD HEADERS (MATCH XLSX)
    //           ========================================================= */
    //         $headers = [
    //             'S.No',
    //             'Campaign No',
    //             'Domain',
    //             'Post URL',
    //         ];

    //         for ($i = 1; $i <= $maxPairs; $i++) {
    //             if ($i === 1) {
    //                 $headers[] = 'Keyword';
    //                 $headers[] = 'URL';
    //             } else {
    //                 $headers[] = "Keyword {$i}";
    //                 $headers[] = "URL {$i}";
    //             }
    //         }

    //         $headers[] = 'Status';
    //         $headers[] = 'Date';

    //         $writer->addRow($headers);

    //         /* =========================================================
    //        3️⃣ FILL ROWS (SAFE & CONSISTENT)
    //           ======================================================== */
    //         $sno = 1;

    //         foreach ($posts as $post) {

    //             $row = [
    //                 $sno++,
    //                 $campaign->campaign_no,
    //                 optional($post->campaignDomain?->domain)->name ?? '-',
    //                 $post->remote_url ?? '-',
    //             ];

    //             $ca = $post->campaignArticle;

    //             if ($ca && $ca->keyword_type === 'json') {
    //                 $keywords = json_decode($ca->keyword ?? '[]', true) ?? [];
    //                 $urls     = json_decode($ca->url ?? '[]', true) ?? [];
    //             } else {
    //                 $keywords = [$ca->keyword ?? ''];
    //                 $urls     = [$ca->url ?? ''];
    //             }

    //             for ($i = 0; $i < $maxPairs; $i++) {
    //                 $row[] = $keywords[$i] ?? '';
    //                 $row[] = $urls[$i] ?? '';
    //             }

    //             $row[] = $post->status === 'success' ? 'Live' : 'Not Live';
    //             $row[] = optional($post->created_at)->format('d M Y');

    //             $writer->addRow($row);
    //         }

    //         $writer->close();
    //     }, $fileName, [
    //         'Content-Type' => 'text/csv',
    //     ]);
    // }



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
