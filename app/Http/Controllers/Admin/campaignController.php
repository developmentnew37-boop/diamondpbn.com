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
use App\Jobs\DeleteCampaignJob;
use App\Jobs\PublishCampaignPostJob;
use App\Services\CampaignPostContentBuilder;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleLanguage;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;

class campaignController extends Controller
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

        if (!$admin->isSuperAdmin()) {
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

        foreach ($articles as $ca) {

            $k = $ca->keyword === null ? '' : (string) $ca->keyword;
            $u = $ca->url === null ? '' : (string) $ca->url;
            $key = $k . "\n" . $u;

            if (!isset($batches[$key])) {
                $batches[$key] = [
                    'keyword' => $k,
                    'url' => $u,
                    'keyword_type' => $ca->keyword_type,
                    'url_type' => $ca->url_type,
                    'count' => 0,
                    'campaign_article_ids' => [],
                    'campaign_post_ids' => [],
                    'representative_id' => $ca->id,
                ];
            }

            $batches[$key]['count']++;
            $batches[$key]['campaign_article_ids'][] = $ca->id;

            foreach ($ca->campaignPosts as $post) {
                $batches[$key]['campaign_post_ids'][] = $post->id;
            }
        }

        // remove duplicate post IDs
        foreach ($batches as &$batch) {
            $batch['campaign_post_ids'] = array_values(
                array_unique($batch['campaign_post_ids'])
            );
        }

        $distinctBatches = array_values($batches);

        return view(
            'admin.campaigns.pbn-post.edit-campaign',
            compact('campaign', 'distinctBatches')
        );
    }

    // this is single post page edit function where we fetch data from remote site 
    public function editCampaignPost(string $id)
    {
        //
        $campaignPost = CampaignPost::find($id);
        $campaign = Campaign::find($campaignPost->campaign_id);
        // first taking out the domain name so we have to make get request to domain with campaign post id
        $domainName = optional($campaignPost->campaignDomain?->domain)->name;
        $api_key = optional($campaignPost->campaignDomain?->domain)->api_key;

        // dd($campaignPost->remote_id);

        $url = "https://{$domainName}/wp-json/external/v1/posts/{$campaignPost->remote_id}?api_key={$api_key}";

        $response = Http::withoutVerifying()->timeout(40)->get($url);

        $fetchedData = $response->json();

        // dd($fetchData);

        return view('admin.campaigns.pbn-post.edit-campaign-post', compact('campaignPost', 'campaign', 'fetchedData'));
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
     * Bulk update: update campaign_articles in DB, then queue remote post updates (job per batch).
     * Supports single and json (multiple keyword/URL) batches.
     */
    public function bulkUpdateCampaignPosts(Request $request, string $id)
    {
        $campaign = Campaign::find($id);
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
        $queuedBatches = 0;

        foreach ($representativeIds as $index => $repId) {
            $repId = (int) $repId;
            $representative = CampaignArticle::where('campaign_id', $campaign->id)->find($repId);
            if (!$representative) {
                continue;
            }

            // New pairs: support single (batch_keyword[index] string) or json (batch_keyword[index] array)
            $kwInput = $batchKeywords[$index] ?? null;
            $urlInput = $batchUrls[$index] ?? null;
            if (is_array($kwInput) && is_array($urlInput)) {
                $newKwList  = array_values(array_map(fn($v) => trim((string) $v), $kwInput));
                $newUrlList = array_values(array_map(fn($v) => trim((string) $v), $urlInput));
                $newPairs = [];
                $len = min(count($newKwList), count($newUrlList));
                for ($i = 0; $i < $len; $i++) {
                    $newPairs[] = [$newKwList[$i] ?? '', $newUrlList[$i] ?? ''];
                }
            } else {
                $newKw  = trim((string) $kwInput);
                $newUrl = trim((string) $urlInput);
                $newPairs = ($newKw !== '' || $newUrl !== '') ? [[$newKw, $newUrl]] : [];
            }

            $oldKeyword = $representative->keyword === null ? '' : (string) $representative->keyword;
            $oldUrl     = $representative->url === null ? '' : (string) $representative->url;
            $isJson     = ($representative->keyword_type ?? '') === 'json';

            $oldPairs = [];
            if ($isJson) {
                $kwArr = json_decode($oldKeyword, true);
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

            $removePairs = [];
            $replacePairs = [];
            $addPairs = [];
            for ($i = 0; $i < count($oldPairs); $i++) {
                if ($i >= count($newPairs)) {
                    $removePairs[] = $oldPairs[$i];
                } elseif (($newPairs[$i][0] ?? '') === '' && ($newPairs[$i][1] ?? '') === '') {
                    $removePairs[] = $oldPairs[$i];
                } elseif (($oldPairs[$i][0] ?? '') !== ($newPairs[$i][0] ?? '') || ($oldPairs[$i][1] ?? '') !== ($newPairs[$i][1] ?? '')) {
                    $replacePairs[] = [
                        $oldPairs[$i][0],
                        $oldPairs[$i][1],
                        $newPairs[$i][0] ?? '',
                        $newPairs[$i][1] ?? '',
                    ];
                }
            }
            for ($i = count($oldPairs); $i < count($newPairs); $i++) {
                if (($newPairs[$i][0] ?? '') !== '' || ($newPairs[$i][1] ?? '') !== '') {
                    $addPairs[] = $newPairs[$i];
                }
            }

            $hasChanges = count($removePairs) > 0 || count($replacePairs) > 0 || count($addPairs) > 0
                || count($newPairs) !== count($oldPairs);
            if (!$hasChanges && count($newPairs) === count($oldPairs)) {
                continue;
            }

            $caIds = CampaignArticle::where('campaign_id', $campaign->id)
                ->where('keyword', $representative->keyword)
                ->where('url', $representative->url)
                ->pluck('id')
                ->all();

            $pairsToStore = array_values(array_filter($newPairs, fn($p) => ($p[0] ?? '') !== '' || ($p[1] ?? '') !== ''));
            $newIsJson = count($pairsToStore) > 1;
            if (count($pairsToStore) === 0) {
                $kwStore  = $newIsJson ? '[]' : '';
                $urlStore = $newIsJson ? '[]' : '';
            } elseif (count($pairsToStore) === 1 && !$newIsJson) {
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
                // Show "Updated" immediately for posts in this batch; job will clear on remote failure
                CampaignPost::whereIn('id', $postIds)->update(['content_updated_at' => now()]);
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
            ->with($queuedBatches > 0 ? 'cus__success' : 'cus__error', $msg);
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

        return back()->with('cus__success', 'Successfully updated the campaign');
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
}
