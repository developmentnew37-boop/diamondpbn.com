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
use App\Jobs\BulkUpdateCampaignPostsJob;
use App\Jobs\BulkRetryCampaignPostsJob;
use App\Http\Controllers\Admin\Concerns\AuthorizesAdminCampaign;
use App\Http\Controllers\Admin\Concerns\ValidatesBulkCampaignIds;
use App\Jobs\DeleteCampaignJob;
use App\Jobs\PublishCampaignPostJob;
use App\Services\PurgeLocalCampaignDataService;
use App\Services\CampaignKeywordPairValidator;
use App\Services\CampaignPostContentBuilder;
use App\Support\WordPressApiFetchedPost;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleLanguage;
use App\Http\Controllers\Admin\Concerns\AppliesSuperAdminCampaignOwnerFilter;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;

class campaignController extends Controller
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

        $ownerData = $this->scopeCampaignQueryForOwner($query, $request, $admin);

        // ✅ Paginate + keep query params
        $campaigns = $query
            ->orderByDesc('id')
            ->paginate($limit)
            ->appends($request->all());

        // ✅ Offset (for serial numbers in table)
        $offset = ($campaigns->currentPage() - 1) * $limit;

        return view(
            'admin.campaigns.pbn-post.campaign',
            array_merge(compact('campaigns', 'offset'), $ownerData)
        );
    }



    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $campaignId = 'CMP-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        $adminId = auth('admin')->id();

        // Cache the create campaign data for 10 minutes to improve performance
        $cacheKey = 'campaign_create_data_' . $adminId;

        $data = cache()->remember($cacheKey, 600, function () use ($adminId) {
            // Optimize: Load only necessary columns
            $domainCategory = DomainCategory::select('id', 'name')->get();
            $articleCategory = ArticleCategory::select('id', 'name')->get();

            // Article sets with count - using proper many-to-many relationship through pivot table
            // Note: Removed admin_id filter to allow admins/super admins to access all article sets
            $articleSet = DB::table('article_sets')
                ->leftJoin('article_set_items', 'article_sets.id', '=', 'article_set_items.article_set_id')
                ->leftJoin('articles', function($join) {
                    $join->on('article_set_items.article_id', '=', 'articles.id')
                         ->where('articles.status', '!=', 1)
                         ->whereNull('articles.deleted_at')
                         ->whereNull('articles.lock_at');
                })
                ->select('article_sets.id', 'article_sets.name', DB::raw('COUNT(articles.id) as articles_count'))
                ->groupBy('article_sets.id', 'article_sets.name')
                ->get();

            // Optimize: Load only necessary columns
            // Note: Removed admin_id filter to allow admins/super admins to access all domain sets
            $domainSets = DomainSet::select('id', 'name')->get();

            // Optimize: Use direct join query for article languages
            // Note: Removed admin_id filter to allow admins/super admins to access all articles
            $articleLanguages = DB::table('article_languages')
                ->leftJoin('articles', function($join) {
                    $join->on('article_languages.id', '=', 'articles.article_language_id')
                         ->where('articles.status', 0)
                         ->whereNull('articles.deleted_at')
                         ->whereNull('articles.lock_at');
                })
                ->select('article_languages.id', 'article_languages.name', DB::raw('COUNT(articles.id) as article_count'))
                ->groupBy('article_languages.id', 'article_languages.name')
                ->having('article_count', '>', 0)
                ->get();

            return compact('domainCategory', 'articleCategory', 'articleSet', 'domainSets', 'articleLanguages');
        });

        $is_sticky = 0;

        return view('admin.campaigns.pbn-post.create-campaign', array_merge(
            compact('campaignId', 'is_sticky'),
            $data
        ));
    }

    /**
     * Store a newly created resource in storage.
     */

    private function generateUniqueCampaignNo(string $input, ?int $ignoreId = null): string
    {
        $base = Str::slug($input);

        if ($base === '') {
            $base = 'campaign-' . now()->timestamp;
        }

        $slug = $base;
        $counter = 1;

        while (
            Campaign::where('campaign_no', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
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

            'keywordmethod'         => 'nullable|string|in:normal,bulk,multiple,multi_bulk',
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
            $method = (string) ($request->keywordmethod ?? 'normal'); // normal | bulk | multiple | multi_bulk
            $isMultiple = in_array($method, ['multiple', 'multi_bulk'], true);

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

                $articleRow = Article::find($articleId);
                if (! $articleRow) {
                    throw new \Exception("Article {$articleId} not found.");
                }

                $ca = CampaignArticle::create([
                    'campaign_id'              => $campaign->id,
                    'article_id'               => (int) $articleId,
                    'article_title_snapshot'   => $articleRow->name,
                    'article_body_snapshot'    => $articleRow->description,
                    'keyword'                  => $kwStore,
                    'url'                      => $urlStore,
                    'media'                    => $row['media'] ?? null,
                    'keyword_type'             => $kwType,
                    'url_type'                 => $urlType,
                    'nofollow'                 => ! empty($row['nofollow']),
                    'sponsored'                => ! empty($row['sponsored']),
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
            ->with(['campaignDomain.domain', 'campaignArticle.article'])
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




        // 4️⃣ Max keyword/URL pairs across posts (mixed single + multi-link in one campaign)
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

        $keywordType = $maxKeywordCount > 1 ? 'json' : 'single';

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

    public function edit(string $id)
    {
        $campaign = Campaign::findOrFail($id);

        $articles = CampaignArticle::with('campaignPosts')
            ->where('campaign_id', $campaign->id)
            ->get();
        $batches = [];
        $hasJsonKeywords = false;
        foreach ($articles as $ca) {
            $k = $ca->keyword === null ? '' : (string) $ca->keyword;
            $u = $ca->url === null ? '' : (string) $ca->url;
            $key = $k . "\n" . $u;
            if (($ca->keyword_type ?? 'single') === 'json') {
                $hasJsonKeywords = true;
            }

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
            foreach ($ca->campaignPosts as $post) {
                $batches[$key]['post_ids'][] = $post->id;
            }
        }
        foreach ($batches as &$batch) {
            $batch['post_ids'] = array_values(array_unique($batch['post_ids']));
        }
        unset($batch);
        $distinctBatches = array_values($batches);

        $orderedArticleIds = $this->orderedCampaignArticleIdsForCampaign($campaign);
        $postQuantity = count($orderedArticleIds);

        $multiLevelBoxes = $postQuantity > 0
            ? $this->buildMultiLevelBoxesForEdit($orderedArticleIds)
            : [];
        $preferredKeywordTab = $hasJsonKeywords ? 'multi' : 'batch';

        $initialNofollow = false;
        if ($postQuantity > 0) {
            $firstCa = CampaignArticle::find($orderedArticleIds[0]);
            $initialNofollow = $firstCa && (bool) $firstCa->nofollow;
        }

        return view(
            'admin.campaigns.pbn-post.edit-campaign',
            compact('campaign', 'postQuantity', 'multiLevelBoxes', 'initialNofollow', 'distinctBatches', 'preferredKeywordTab')
        );
    }

    // this is single post page edit function where we fetch data from remote site 
    public function editCampaignPost(string $id)
    {
        //
        $campaignPost = CampaignPost::with('campaignArticle')->find($id);
        $campaign = Campaign::find($campaignPost->campaign_id);
        // first taking out the domain name so we have to make get request to domain with campaign post id
        $domainName = optional($campaignPost->campaignDomain?->domain)->name;
        $api_key = optional($campaignPost->campaignDomain?->domain)->api_key;

        // dd($campaignPost->remote_id);

        $url = "https://{$domainName}/wp-json/external/v1/posts/{$campaignPost->remote_id}?api_key={$api_key}";

        $response = Http::withoutVerifying()->timeout(40)->get($url);

        $data = $response->json();
        if (! is_array($data)) {
            return back()->with('cus__error', 'Remote site did not return valid post data.');
        }
        $fetchedData = WordPressApiFetchedPost::normalizeForEditForm($data);

        $keywordPairs = [];
        $ca = $campaignPost->campaignArticle;
        if ($ca) {
            if (($ca->keyword_type ?? '') === 'json') {
                $kwDec  = json_decode($ca->keyword, true);
                $urlDec = json_decode($ca->url, true);
                if (is_array($kwDec) && is_array($urlDec)) {
                    $n = min(count($kwDec), count($urlDec));
                    for ($i = 0; $i < $n; $i++) {
                        $keywordPairs[] = [
                            'keyword' => (string) ($kwDec[$i] ?? ''),
                            'url'     => (string) ($urlDec[$i] ?? ''),
                        ];
                    }
                }
            } else {
                $keywordPairs[] = [
                    'keyword' => (string) ($ca->keyword ?? ''),
                    'url'     => (string) ($ca->url ?? ''),
                ];
            }
        }
        if (count($keywordPairs) === 0) {
            $keywordPairs[] = ['keyword' => '', 'url' => ''];
        }

        // dd($fetchData);

        return view('admin.campaigns.pbn-post.edit-campaign-post', compact('campaignPost', 'campaign', 'fetchedData', 'keywordPairs'));
    }
    // this is single post page update(submit) function where we fetch data from remote site and then update it in remote site also

    public function updateCampaignPost(Request $request, string $id)
    {
        //
        $campaignPost = CampaignPost::find($id);

        if (!$campaignPost) {
            return back()->with('cus__error', 'The requested campaign post is invalid');
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
        ]);

        // first taking out the domain name so we have to make get request to domain with campaign post id
        $domainName = optional($campaignPost->campaignDomain?->domain)->name;
        $api_key = optional($campaignPost->campaignDomain?->domain)->api_key;

        if (!$domainName || !$api_key) {
            return back()->with(
                'cus__error',
                'Domain or API key is missing for this campaign post.'
            );
        }



        $url = "https://{$domainName}/wp-json/external/v1/posts/update/{$campaignPost->remote_id}?api_key={$api_key}";

        // dd($url);

        $response = Http::withoutVerifying()
            ->timeout(120)
            ->asJson()
            ->post($url, [
                'title'   => $validated['name'],
                'content' => $validated['description'], // raw HTML ✅
            ]);


        if ($response->failed()) {
            return back()->with('cus__error', 'Failed to update the post on the remote domain.');
        }

        $campaignPost->update(['content_updated_at' => now()]);

        return redirect()
            ->route('admin.campaign.show', $campaignPost->campaign_id)
            ->with('cus__success', "Successfully updated the campaign post on {$domainName}.");
    }



    public function deleteCampaignPost(string $id)
    {
        $campaignPost = CampaignPost::with(['campaign'])->find($id);

        if (!$campaignPost) {
            return back()->with('cus__error', 'The requested campaign post is invalid');
        }

        $campaign = $campaignPost->campaign;
        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        $postStatus = $campaignPost->status;
        $campaignId = $campaign->id;
        $campaignDeleted = false;

        DB::transaction(function () use ($campaignPost, $campaignId, $postStatus, &$campaignDeleted) {

            /** 🔒 Lock campaign row so counts stay correct */
            $campaign = Campaign::lockForUpdate()->find($campaignId);
            if (!$campaign) {
                throw new \RuntimeException('Campaign not found');
            }

            /** If this is the last post, we will delete the campaign after removing the post */
            $isLastPost = $campaign->total_targets === 1;

            $campaignArticle = CampaignArticle::find($campaignPost->campaign_article_id);
            $campaignDomain  = CampaignDomain::find($campaignPost->campaign_domain_id);

            /** 🔓 Unlock article if queued */
            if ($campaignArticle && $postStatus === 'queued') {
                $article = Article::find($campaignArticle->article_id);
                if ($article) {
                    $article->update([
                        'lock_at' => null,
                        'status'  => 0,
                    ]);
                }
            }

            /** 📉 Decrement campaign counts by post status (total_targets always; completed/failed by status) */
            if ($campaign->total_targets > 0) {
                $campaign->decrement('total_targets');
            }
            if ($postStatus === 'success' && $campaign->completed_targets > 0) {
                $campaign->decrement('completed_targets');
            }
            if ($postStatus === 'failed' && $campaign->failed_targets > 0) {
                $campaign->decrement('failed_targets');
            }

            /** 🌐 Delete remote WP post if published */
            if ($postStatus === 'success') {
                $domain = $campaignPost->campaignDomain?->domain;

                if ($domain && $domain->api_key && $campaignPost->remote_id) {
                    $url = "https://{$domain->name}/wp-json/external/v1/posts/delete/{$campaignPost->remote_id}?api_key={$domain->api_key}";

                    $response = Http::withoutVerifying()
                        ->timeout(120)
                        ->asJson()
                        ->delete($url);

                    if ($response->failed()) {
                        throw new \Exception(
                            'Remote WordPress delete failed: ' . $response->body()
                        );
                    }
                }
            }

            /** 🗑️ Local DB deletes */
            if ($campaignArticle) {
                $campaignArticle->delete();
            }

            if ($campaignDomain) {
                $campaignDomain->delete();
            }

            $campaignPost->delete();

            /** 🗑️ If this was the last post, remove the campaign too */
            if ($isLastPost) {
                $campaign->delete();
                $campaignDeleted = true;
            }
        });

        if ($campaignDeleted) {
            return redirect()->route('admin.campaign.index')->with(
                'cus__success',
                'Successfully deleted the campaign post. The campaign had no posts left and was also removed.'
            );
        }

        return back()->with(
            'cus__success',
            'Successfully deleted the campaign post and its associated data.'
        );
    }

    /**
     * Apply one keyword/URL batch (bulk edit or single-post edit).
     * When $onlyArticleIds is set, only those campaign_article rows are updated (single-post / split batch).
     *
     * @param  array<int>|null  $onlyArticleIds
     * @return array{queued: bool, skipped: bool, error?: string}
     */
    private function applyCampaignKeywordUrlBatch(
        Campaign $campaign,
        CampaignArticle $representative,
        $kwInput,
        $urlInput,
        ?array $onlyArticleIds = null
    ): array {
        if (is_array($kwInput) && is_array($urlInput)) {
            $newKwList  = array_values(array_map(fn ($v) => trim((string) $v), $kwInput));
            $newUrlList = array_values(array_map(fn ($v) => trim((string) $v), $urlInput));
            $newPairs   = [];
            $len        = min(count($newKwList), count($newUrlList));
            for ($i = 0; $i < $len; $i++) {
                $newPairs[] = [$newKwList[$i] ?? '', $newUrlList[$i] ?? ''];
            }
        } else {
            $newKw  = trim((string) $kwInput);
            $newUrl = trim((string) $urlInput);
            $newPairs = ($newKw !== '' || $newUrl !== '') ? [[$newKw, $newUrl]] : [];
        }

        if ($msg = CampaignKeywordPairValidator::validateEditPairs($newPairs)) {
            return ['queued' => false, 'skipped' => false, 'error' => $msg];
        }

        $oldKeyword = $representative->keyword === null ? '' : (string) $representative->keyword;
        $oldUrl     = $representative->url === null ? '' : (string) $representative->url;
        $isJson     = ($representative->keyword_type ?? '') === 'json';

        $oldPairs = [];
        if ($isJson) {
            $kwArr  = json_decode($oldKeyword, true);
            $urlArr = json_decode($oldUrl, true);
            if (is_array($kwArr) && is_array($urlArr)) {
                $n = min(count($kwArr), count($urlArr));
                for ($i = 0; $i < $n; $i++) {
                    $oldPairs[] = [trim((string) ($kwArr[$i] ?? '')), trim((string) ($urlArr[$i] ?? ''))];
                }
            }
        } else {
            if ($oldKeyword !== '' || $oldUrl !== '') {
                $oldPairs[] = [$oldKeyword, $oldUrl];
            }
        }

        $pairsToStore = array_values(array_filter(
            $newPairs,
            fn ($p) => trim((string) ($p[0] ?? '')) !== '' && trim((string) ($p[1] ?? '')) !== ''
        ));

        $removePairs  = [];
        $replacePairs = [];
        $addPairs     = [];

        // Match exact old/new pairs first (handles middle-row delete without false remove of shifted links).
        $oldUsed = array_fill(0, count($oldPairs), false);
        $newUsed = array_fill(0, count($pairsToStore), false);
        $oldTokenMap = [];
        foreach ($oldPairs as $oi => $op) {
            $token = (string) ($op[0] ?? '') . "\n" . (string) ($op[1] ?? '');
            if (! isset($oldTokenMap[$token])) {
                $oldTokenMap[$token] = [];
            }
            $oldTokenMap[$token][] = $oi;
        }
        foreach ($pairsToStore as $ni => $np) {
            $token = (string) ($np[0] ?? '') . "\n" . (string) ($np[1] ?? '');
            if (! empty($oldTokenMap[$token])) {
                $oi = array_shift($oldTokenMap[$token]);
                if ($oi !== null && isset($oldUsed[$oi]) && ! $oldUsed[$oi]) {
                    $oldUsed[$oi] = true;
                    $newUsed[$ni] = true;
                }
            }
        }

        $oldUnmatched = [];
        foreach ($oldPairs as $oi => $op) {
            if (! ($oldUsed[$oi] ?? false)) {
                $oldUnmatched[] = $op;
            }
        }
        $newUnmatched = [];
        foreach ($pairsToStore as $ni => $np) {
            if (! ($newUsed[$ni] ?? false)) {
                $newUnmatched[] = $np;
            }
        }

        $replaceCount = min(count($oldUnmatched), count($newUnmatched));
        for ($i = 0; $i < $replaceCount; $i++) {
            $replacePairs[] = [
                $oldUnmatched[$i][0] ?? '',
                $oldUnmatched[$i][1] ?? '',
                $newUnmatched[$i][0] ?? '',
                $newUnmatched[$i][1] ?? '',
            ];
        }
        for ($i = $replaceCount; $i < count($oldUnmatched); $i++) {
            $removePairs[] = $oldUnmatched[$i];
        }
        for ($i = $replaceCount; $i < count($newUnmatched); $i++) {
            $addPairs[] = $newUnmatched[$i];
        }

        $hasChanges = count($removePairs) > 0 || count($replacePairs) > 0 || count($addPairs) > 0
            || count($pairsToStore) !== count($oldPairs);
        if (! $hasChanges && count($newPairs) === count($oldPairs)) {
            return ['queued' => false, 'skipped' => true];
        }

        if ($onlyArticleIds !== null) {
            $caIds = CampaignArticle::where('campaign_id', $campaign->id)
                ->whereIn('id', array_map('intval', $onlyArticleIds))
                ->pluck('id')
                ->all();
        } else {
            $caIds = CampaignArticle::where('campaign_id', $campaign->id)
                ->where('keyword', $representative->keyword)
                ->where('url', $representative->url)
                ->pluck('id')
                ->all();
        }

        if (count($caIds) === 0) {
            return ['queued' => false, 'skipped' => true];
        }

        $newIsJson    = count($pairsToStore) > 1;
        if (count($pairsToStore) === 0) {
            $kwStore  = $newIsJson ? '[]' : '';
            $urlStore = $newIsJson ? '[]' : '';
        } elseif (count($pairsToStore) === 1 && ! $newIsJson) {
            $kwStore  = $pairsToStore[0][0];
            $urlStore = $pairsToStore[0][1];
        } else {
            $kwStore  = json_encode(array_column($pairsToStore, 0));
            $urlStore = json_encode(array_column($pairsToStore, 1));
        }

        CampaignArticle::whereIn('id', $caIds)->update([
            'keyword'      => $kwStore,
            'url'          => $urlStore,
            'keyword_type' => $newIsJson ? 'json' : 'single',
            'url_type'     => $newIsJson ? 'json' : 'single',
        ]);

        $postIds = CampaignPost::whereIn('campaign_article_id', $caIds)
            ->where('status', 'success')
            ->whereNotNull('remote_id')
            ->pluck('id')
            ->all();

        $needsRemoteUpdate = count($removePairs) > 0 || count($replacePairs) > 0 || count($addPairs) > 0;
        if (count($postIds) > 0 && $needsRemoteUpdate) {
            BulkUpdateCampaignPostsJob::dispatch($postIds, $replacePairs, $removePairs, $addPairs)
                ->onQueue('bulk_updates');
            CampaignPost::whereIn('id', $postIds)->update(['content_updated_at' => now()]);

            return ['queued' => true, 'skipped' => false];
        }

        return ['queued' => false, 'skipped' => false];
    }

    /**
     * Update keyword/URL for a single campaign post’s article (convert single → multiple links, etc.).
     */
    public function updateCampaignPostKeywords(Request $request, string $id)
    {
        $campaignPost = CampaignPost::with('campaign')->find($id);
        if (! $campaignPost || ! $campaignPost->campaign) {
            return back()->with('cus__error', 'Campaign post not found.');
        }

        $validated = $request->validate([
            'batch_keyword' => 'required|array|min:1',
            'batch_url'     => 'required|array|min:1',
        ]);

        $campaign = $campaignPost->campaign;
        $ca       = CampaignArticle::where('campaign_id', $campaign->id)
            ->whereKey($campaignPost->campaign_article_id)
            ->first();
        if (! $ca) {
            return back()->with('cus__error', 'Campaign article not found.');
        }

        $result = $this->applyCampaignKeywordUrlBatch(
            $campaign,
            $ca,
            $validated['batch_keyword'],
            $validated['batch_url'],
            [$ca->id]
        );

        if (! empty($result['error'])) {
            return back()->with('cus__error', $result['error'])->withInput();
        }

        if ($result['skipped']) {
            return back()->with('cus__error', 'No keyword/URL changes were made.');
        }

        if ($result['queued']) {
            $campaign->update(['last_bulk_updated_at' => now()]);
        }

        $msg = $result['queued']
            ? 'Keywords/URLs saved. Remote update queued. Run: php artisan queue:work --queue=bulk_updates'
            : 'Keywords/URLs saved. No published post to sync on remote, or no link text changes.';

        return redirect()
            ->route('admin.campaign.edit.post', $campaignPost->id)
            ->with('cus__success', $msg);
    }

    /**
     * Bulk update: update campaign_articles in DB, then queue remote post updates (job per batch).
     * Supports single and json (multiple keyword/URL) batches.
     */
    public function bulkUpdateCampaignPosts(Request $request, string $id)
    {
        $campaign = Campaign::find($id);
        if (! $campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        $representativeIds = $request->input('batch_representative_id', []);
        $batchKeywords     = $request->input('batch_keyword', []);
        $batchUrls         = $request->input('batch_url', []);

        if (! is_array($representativeIds)) {
            return back()->with('cus__error', 'Invalid form data.');
        }

        $batchKeywords = is_array($batchKeywords) ? array_values($batchKeywords) : [];
        $batchUrls     = is_array($batchUrls) ? array_values($batchUrls) : [];
        $queuedBatches = 0;

        foreach ($representativeIds as $index => $repId) {
            $repId = (int) $repId;
            $representative = CampaignArticle::where('campaign_id', $campaign->id)->find($repId);
            if (! $representative) {
                continue;
            }

            $kwInput  = $batchKeywords[$index] ?? null;
            $urlInput = $batchUrls[$index] ?? null;

            $result = $this->applyCampaignKeywordUrlBatch($campaign, $representative, $kwInput, $urlInput, null);
            if (! empty($result['error'])) {
                return redirect()
                    ->route('admin.campaign.edit', $campaign->id)
                    ->with('cus__error', $result['error'])
                    ->with('edit_campaign_tab', 'keywords')
                    ->with('edit_campaign_keywords_tab', 'batch');
            }
            if ($result['queued']) {
                $queuedBatches++;
            }
        }

        if ($queuedBatches > 0) {
            $campaign->update(['last_bulk_updated_at' => now()]);
        }

        $msg = $queuedBatches > 0
            ? "Batches updated in database. {$queuedBatches} batch(es) queued for remote sync. Run the queue worker to process: php artisan queue:work --queue=bulk_updates"
            : "No keyword/URL changes were made, or no published posts to update.";

        return redirect()
            ->route('admin.campaign.edit', $campaign->id)
            ->with($queuedBatches > 0 ? 'cus__success' : 'cus__error', $msg)
            ->with('edit_campaign_tab', 'keywords')
            ->with('edit_campaign_keywords_tab', 'batch');
    }

    /**
     * Edit campaign: same multi-level keyword/URL model as create (Apply for × boxes → one row per post in domain order).
     */
    public function multiLevelUpdateCampaignKeywords(Request $request, string $id)
    {
        $campaign = Campaign::find($id);
        if (! $campaign) {
            return redirect()
                ->route('admin.campaign.index')
                ->with('cus__error', 'Campaign not found');
        }

        $request->validate([
            'keywordmethod'      => 'required|in:multiple',
            'keywordsDataHolder'   => 'required|string',
        ]);

        $keywords = json_decode((string) $request->keywordsDataHolder, true);
        if (! is_array($keywords)) {
            return redirect()
                ->route('admin.campaign.edit', $campaign->id)
                ->with('cus__error', 'Invalid keywords JSON.')
                ->with('edit_campaign_tab', 'keywords')
                ->with('edit_campaign_keywords_tab', 'multi');
        }

        $orderedIds = $this->orderedCampaignArticleIdsForCampaign($campaign);
        if (count($orderedIds) === 0) {
            return redirect()
                ->route('admin.campaign.edit', $campaign->id)
                ->with('cus__error', 'No campaign articles found.')
                ->with('edit_campaign_tab', 'keywords')
                ->with('edit_campaign_keywords_tab', 'multi');
        }

        if (count($keywords) !== count($orderedIds)) {
            return redirect()
                ->route('admin.campaign.edit', $campaign->id)
                ->with(
                    'cus__error',
                    'Keyword rows must be exactly '.count($orderedIds).' (your campaign post count).'
                )
                ->with('edit_campaign_tab', 'keywords')
                ->with('edit_campaign_keywords_tab', 'multi');
        }

        $queuedJobs = 0;

        try {
            DB::transaction(function () use ($campaign, $keywords, $orderedIds, &$queuedJobs) {
                foreach ($orderedIds as $i => $articleId) {
                    $ca = CampaignArticle::where('campaign_id', $campaign->id)->find($articleId);
                    if (! $ca) {
                        throw new \RuntimeException('Campaign article missing.');
                    }

                    $row = $keywords[$i] ?? [];

                    $result = $this->applyCampaignKeywordUrlBatch(
                        $campaign,
                        $ca,
                        $row['keyword'] ?? null,
                        $row['url'] ?? null,
                        [$ca->id]
                    );

                    if (! empty($result['error'])) {
                        throw new \RuntimeException($result['error']);
                    }

                    if (! empty($result['queued'])) {
                        $queuedJobs++;
                    }

                    $mediaVal = isset($row['media']) ? trim((string) $row['media']) : '';
                    $mediaVal = $mediaVal === '' ? null : $mediaVal;
                    $nofollow = ! empty($row['nofollow']);
                    $sponsored = array_key_exists('sponsored', $row)
                        ? ! empty($row['sponsored'])
                        : (bool) ($ca->sponsored ?? false);

                    CampaignArticle::where('id', $ca->id)->update([
                        'media'    => $mediaVal,
                        'nofollow' => $nofollow,
                        'sponsored' => $sponsored,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.campaign.edit', $campaign->id)
                ->with('cus__error', $e->getMessage())
                ->with('edit_campaign_tab', 'keywords')
                ->with('edit_campaign_keywords_tab', 'multi');
        }

        if ($queuedJobs > 0) {
            $campaign->update(['last_bulk_updated_at' => now()]);
        }

        $msg = $queuedJobs > 0
            ? "Keywords updated. {$queuedJobs} remote sync job(s) queued. Run: php artisan queue:work --queue=bulk_updates"
            : 'Keywords and media saved. No remote link changes were needed for published posts, or no published posts yet.';

        return redirect()
            ->route('admin.campaign.edit', $campaign->id)
            ->with('cus__success', $msg)
            ->with('edit_campaign_tab', 'keywords')
            ->with('edit_campaign_keywords_tab', 'multi');
    }

    /**
     * Campaign posts in creation order (domain sort_order), one article id per post.
     *
     * @return array<int, int>
     */
    private function orderedCampaignArticleIdsForCampaign(Campaign $campaign): array
    {
        return CampaignPost::query()
            ->where('campaign_posts.campaign_id', $campaign->id)
            ->join('campaign_domains', 'campaign_posts.campaign_domain_id', '=', 'campaign_domains.id')
            ->orderBy('campaign_domains.sort_order')
            ->pluck('campaign_posts.campaign_article_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $orderedArticleIds
     * @return array<int, array{quantity: int, media: ?string, nofollow: bool, sponsored: bool, rows: array<int, array{url: string, keyword: string}>}>
     */
    private function buildMultiLevelBoxesForEdit(array $orderedArticleIds): array
    {
        if (count($orderedArticleIds) === 0) {
            return [];
        }

        $articles = CampaignArticle::whereIn('id', $orderedArticleIds)->get()->keyBy('id');

        $boxes = [];
        $groupSig = null;
        $groupQty = 0;
        /** @var array|null $groupPayload */
        $groupPayload = null;

        $flush = function () use (&$boxes, &$groupSig, &$groupQty, &$groupPayload) {
            if ($groupSig === null || $groupPayload === null) {
                return;
            }
            $boxes[] = [
                'quantity' => $groupQty,
                'media'    => $groupPayload['media'],
                'nofollow' => $groupPayload['nofollow'],
                'sponsored' => $groupPayload['sponsored'],
                'rows'     => $groupPayload['rows'],
            ];
        };

        foreach ($orderedArticleIds as $aid) {
            $ca = $articles->get($aid);
            if (! $ca) {
                continue;
            }

            $payload = $this->articleRowPayloadForEdit($ca);
            $sig = $this->signatureForMultiLevelPayload($payload);

            if ($groupSig === null) {
                $groupSig = $sig;
                $groupPayload = $payload;
                $groupQty = 1;
            } elseif ($groupSig === $sig) {
                $groupQty++;
            } else {
                $flush();
                $groupSig = $sig;
                $groupPayload = $payload;
                $groupQty = 1;
            }
        }
        $flush();

        return $boxes;
    }

    /**
     * @return array{media: ?string, nofollow: bool, sponsored: bool, rows: array<int, array{url: string, keyword: string}>}
     */
    private function articleRowPayloadForEdit(CampaignArticle $ca): array
    {
        $kw  = $ca->keyword;
        $url = $ca->url;

        if (($ca->keyword_type ?? '') === 'json') {
            $kwArr  = json_decode((string) $kw, true);
            $urlArr = json_decode((string) $url, true);
            if (! is_array($kwArr)) {
                $kwArr = [];
            }
            if (! is_array($urlArr)) {
                $urlArr = [];
            }
        } else {
            $kwArr  = [trim((string) $kw)];
            $urlArr = [trim((string) $url)];
        }

        $n = min(count($kwArr), count($urlArr));
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = [
                'url'     => (string) ($urlArr[$i] ?? ''),
                'keyword' => (string) ($kwArr[$i] ?? ''),
            ];
        }
        if (count($rows) === 0) {
            $rows[] = ['url' => '', 'keyword' => ''];
        }

        $media = $ca->media;
        $media = $media === null || trim((string) $media) === '' ? null : trim((string) $media);

        return [
            'media'    => $media,
            'nofollow' => (bool) $ca->nofollow,
            'sponsored' => (bool) ($ca->sponsored ?? false),
            'rows'     => $rows,
        ];
    }

    /**
     * @param  array{media: ?string, nofollow: bool, sponsored: bool, rows: array<int, array{url: string, keyword: string}>}  $payload
     */
    private function signatureForMultiLevelPayload(array $payload): string
    {
        return json_encode(
            [
                'm'    => $payload['media'],
                'nf'   => $payload['nofollow'],
                'sp'   => $payload['sponsored'],
                'rows' => $payload['rows'],
            ],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Update the specified resource in storage.
     * this is for updating the campaign no from edit campaign page
     */
    public function update(Request $request, string $id)
    {
        $campaign = Campaign::find($id);


        if (!$campaign) {
            return back()->with('cus__error', 'The requested campaign is invalid');
        }

        $validated = $request->validate([
            'campaign_no' => [
                'required',
                'string',
                Rule::unique('campaigns', 'campaign_no')->ignore($campaign->id),
            ],
        ]);

        $campaign_no = $this->generateUniqueCampaignNo(
            $validated['campaign_no'],
            $campaign->id
        );

        $campaign->update([
            'campaign_no' => $campaign_no,
        ]);

        return back()
            ->with('cus__success', 'Successfully updated the campaign')
            ->with('edit_campaign_tab', 'campaign');
    }

    /** Manual retry: allow queued/failed/publishing so user can re-dispatch after jobs killed. Resets attempts and runs from first. */
    public function retry(string $id)
    {
        $retryPost = CampaignPost::with('campaign')->find($id);

        if (!$retryPost) {
            return back()->with('cus__error', 'The post is invalid or not present in the database');
        }

        if ($retryPost->status === 'success') {
            return back()->with('cus__error', 'Successful posts do not need retry');
        }

        if (in_array($retryPost->campaign->status ?? '', ['paused', 'cancelled'], true)) {
            return back()->with('cus__error', 'Cannot retry: campaign is paused or cancelled');
        }

        $retryPost->update([
            'status'         => 'queued',
            'attempt_count'  => 0,
            'next_retry_at'  => null,
            'locked_at'      => null,
            'lock_token'     => null,
            'last_error'     => null,
        ]);

        PublishCampaignPostJob::dispatch($retryPost->id)->onQueue('campaigns');

        return back()->with(
            'cus__success',
            'Post retry queued successfully and will run from first.'
        );
    }



    /**
     * Queue campaign deletion: remote deletes + DB cleanup run in background (DeleteCampaignJob).
     */
    public function destroy(string $id)
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        DeleteCampaignJob::dispatch($campaign->id)->onQueue('deletions');

        return redirect()
            ->route('admin.campaign.index')
            ->with('cus__success', 'Campaign deletion queued. Posts will be removed from remote sites and the database in the background. Run the queue worker to process it.');
    }

    /**
     * Remove campaign data from this application only. Does not call remote sites; published posts stay live.
     */
    public function purgeLocalOnly(string $id)
    {
        $campaign = Campaign::find($id);
        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }
        $this->authorizeCampaignAccess($campaign);
        PurgeLocalCampaignDataService::purgePbnCampaign((int) $campaign->id);

        return redirect()
            ->route('admin.campaign.index')
            ->with(
                'cus__success',
                'Campaign removed from this dashboard only. Remote posts were not deleted.'
            );
    }

    /**
     * Remove multiple PBN campaigns from the database only (no remote API calls).
     */
    public function bulkPurgeLocal(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return back()->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, Campaign::class);
        if ($allowed === []) {
            return back()->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        foreach ($allowed as $id) {
            PurgeLocalCampaignDataService::purgePbnCampaign($id);
        }

        $n = count($allowed);

        return redirect()
            ->route('admin.campaign.index')
            ->with('cus__success', $n . ' campaign(s) removed from this dashboard only. Remote posts were not deleted.');
    }

    /**
     * Bulk retry all failed posts across selected campaigns.
     */
    public function bulkRetryFailed(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return back()->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, Campaign::class);
        if ($allowed === []) {
            return back()->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        BulkRetryCampaignPostsJob::dispatch($allowed);

        $n = count($allowed);

        return redirect()
            ->route('admin.campaign.index')
            ->with('cus__success', 'Bulk retry queued for ' . $n . ' campaign(s). All failed posts will be retried in the background. Run the queue worker to process them.');
    }
}
