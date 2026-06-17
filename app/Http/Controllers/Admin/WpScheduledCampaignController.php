<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ValidatesBulkCampaignIds;
use App\Http\Controllers\Controller;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use App\Models\Admin\ArticleSet;
use App\Models\Admin\ArticleLanguage;
use App\Models\Admin\WpScheduledCampaign;
use App\Models\Admin\WpScheduledCampaignDate;
use App\Models\Admin\WpScheduledCampaignDomain;
use App\Models\Admin\WpScheduledCampaignArticle;
use App\Models\Admin\WpScheduledCampaignPost;
use App\Jobs\PublishWpScheduledPostJob;
use App\Jobs\SyncWpScheduledPostStatusJob;
use App\Jobs\DeleteWpScheduledCampaignJob;
use App\Services\PurgeLocalCampaignDataService;
use App\Jobs\BulkUpdateWpScheduledPostsJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelWriter;
use App\Support\WordPressApiFetchedPost;
use App\Services\EditCampaignMultiLevelKeywordState;
use App\Http\Controllers\Admin\Concerns\AppliesSuperAdminCampaignOwnerFilter;

class WpScheduledCampaignController extends Controller
{
    use AppliesSuperAdminCampaignOwnerFilter;
    use ValidatesBulkCampaignIds;

    /** Minutes to add to "now" for today/past posts so WordPress does not mark "Missed schedule" (WP cron runs on visit). */
    private const SCHEDULE_BUFFER_MINUTES = 120; // 2 hours

    public function __construct()
    {
        $this->middleware('can.create.campaigns')->except(['report', 'exportReport']);
    }

    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:150',
            'filter_user' => 'nullable|string|max:20',
        ]);

        $query = WpScheduledCampaign::query()->with('domainCategory');

        if ($request->filled('search')) {
            $query->where('campaign_no', 'LIKE', '%' . trim($request->search) . '%');
        }

        $admin = Auth::guard('admin')->user();
        $ownerData = $this->scopeCampaignQueryForOwner($query, $request, $admin);

        $limit = config('campaign.pagination.default_limit');
        $campaigns = $query->orderByDesc('id')->paginate($limit)->appends($request->all());
        $offset = ($campaigns->currentPage() - 1) * $limit;

        foreach ($campaigns as $c) {
            if (empty($c->report_token)) {
                $c->report_token = Str::random(64);
                $c->save();
            }
        }

        return view(
            'admin.campaigns.wp-scheduled.index',
            array_merge(compact('campaigns', 'offset'), $ownerData)
        );
    }

    public function create()
    {
        $campaignId = 'WP-SCH-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);
        $domainCategory = DomainCategory::all();
        $articleCategory = ArticleCategory::all();
        $articleSet = ArticleSet::withCount('articles')->where('admin_id', auth('admin')->id())->get();
        $domainSets = DomainSet::where('admin_id', Auth::guard('admin')->id())->get();
        $articleLanguages = ArticleLanguage::withCount(['Article' => function ($q) {
            $q->where('status', 0)->whereNull('deleted_at')->whereNull('lock_at');
        }])->having('article_count', '>', 0)->get();

        return view('admin.campaigns.wp-scheduled.create', compact(
            'campaignId', 'domainCategory', 'articleCategory', 'articleSet', 'domainSets', 'articleLanguages'
        ));
    }

    /**
     * Generate a unique campaign_no slug.
     * When $ignoreId is provided, that campaign row is excluded from uniqueness check (for updates).
     */
    private function generateUniqueCampaignNo(string $input, ?int $ignoreId = null): string
    {
        $base = Str::slug($input) ?: 'wp-sch-' . now()->timestamp;
        $slug = $base;
        $counter = 1;

        while (
            WpScheduledCampaign::where('campaign_no', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_no'            => 'required|string',
            'domain_category'       => 'nullable|integer|exists:domain_categories,id',
            'post_quantity'          => 'required|integer|min:1',
            'schedule_from_date'     => 'required|date',
            'schedule_to_date'       => 'required|date|after_or_equal:schedule_from_date',
            'date_quantities'        => 'required|string', // JSON [{"date":"Y-m-d","quantity":n},...]
            'article_niche'          => 'nullable|integer|exists:article_categories,id',
            'sel_articles_opt'       => 'required|in:own_article,system_article,language_article',
            'selected_articles_val'  => 'required|string',
            'keywordmethod'          => 'nullable|in:normal,bulk,multiple,multi_bulk',
            'keywordsDataHolder'     => 'required|string',
            'campaigns_domains'      => 'required|string',
        ]);

        $postQty = (int) $request->post_quantity;
        $from = Carbon::parse($request->schedule_from_date)->startOfDay();
        $to   = Carbon::parse($request->schedule_to_date)->startOfDay();

        $dateQuantities = json_decode($request->date_quantities, true);
        if (!is_array($dateQuantities)) {
            return back()->with('cus__error', 'Invalid date distribution.')->withInput();
        }

        $sum = 0;
        $dateRows = [];
        foreach ($dateQuantities as $row) {
            $d = $row['date'] ?? null;
            $q = (int) ($row['quantity'] ?? 0);
            if (!$d || $q < 0) {
                continue;
            }
            $dateObj = Carbon::parse($d)->startOfDay();
            if ($dateObj->lt($from) || $dateObj->gt($to)) {
                continue;
            }
            $dateRows[] = ['date' => $dateObj->format('Y-m-d'), 'quantity' => $q];
            $sum += $q;
        }

        if ($sum !== $postQty) {
            return back()->with('cus__error', "Total across dates ({$sum}) must equal post quantity ({$postQty}).")->withInput();
        }

        $articleIds = array_values(array_filter(array_map('intval', explode(',', $request->selected_articles_val))));
        $domainIds = json_decode($request->campaigns_domains, true);
        if (!is_array($domainIds)) {
            return back()->with('cus__error', 'Invalid domains.')->withInput();
        }
        $domainIds = array_values(array_map('intval', $domainIds));
        $keywords = json_decode($request->keywordsDataHolder, true);
        if (!is_array($keywords)) {
            return back()->with('cus__error', 'Invalid keywords.')->withInput();
        }

        $method = (string) ($request->keywordmethod ?? 'normal');
        $isMultiple = ($method === 'multiple' || $method === 'multi_bulk');

        if (count($articleIds) !== $postQty || count($domainIds) !== $postQty || count($keywords) !== $postQty) {
            return back()->with('cus__error', 'Articles, domains, and keywords count must match post quantity.')->withInput();
        }

        $campaignNo = $this->generateUniqueCampaignNo($request->campaign_no);

        $campaignId = DB::transaction(function () use (
            $request, $campaignNo, $postQty, $articleIds, $domainIds, $keywords,
            $dateRows, $isMultiple
        ) {
            $campaign = WpScheduledCampaign::create([
                'campaign_no'         => $campaignNo,
                'admin_id'            => auth('admin')->id(),
                'domain_category_id'  => $request->domain_category,
                'article_category_id' => $request->article_niche,
                'schedule_from_date'  => $request->schedule_from_date,
                'schedule_to_date'    => $request->schedule_to_date,
                'total_targets'       => $postQty,
                'status'              => 'queued',
                'completed_targets'   => 0,
                'failed_targets'      => 0,
            ]);

            foreach ($dateRows as $dr) {
                WpScheduledCampaignDate::create([
                    'wp_scheduled_campaign_id' => $campaign->id,
                    'schedule_date'            => $dr['date'],
                    'quantity'                => $dr['quantity'],
                ]);
            }

            $domainMap = [];
            foreach ($domainIds as $i => $domainId) {
                $row = WpScheduledCampaignDomain::create([
                    'wp_scheduled_campaign_id' => $campaign->id,
                    'domain_id'                => $domainId,
                ]);
                $domainMap[$i] = $row->id;
            }

            $articleMap = [];
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

                    $kwArr = array_values(array_filter(array_map(fn($v) => trim((string) $v), $kwArr)));
                    $urlArr = array_values(array_filter(array_map(fn($v) => trim((string) $v), $urlArr)));
                    $kwStore = json_encode($kwArr, JSON_UNESCAPED_UNICODE);
                    $urlStore = json_encode($urlArr, JSON_UNESCAPED_UNICODE);
                    $kwType = 'json';
                    $urlType = 'json';
                } else {
                    $kwStore = is_array($kwVal) ? ($kwVal[0] ?? null) : $kwVal;
                    $urlStore = is_array($urlVal) ? ($urlVal[0] ?? null) : $urlVal;
                    $kwStore = $kwStore !== null ? trim((string) $kwStore) : null;
                    $urlStore = $urlStore !== null ? trim((string) $urlStore) : null;
                    if ($kwStore === '') $kwStore = null;
                    if ($urlStore === '') $urlStore = null;
                    $kwType = 'single';
                    $urlType = 'single';
                }

                $articleRow = Article::find($articleId);
                if (! $articleRow) {
                    throw new \Exception("Article {$articleId} not found.");
                }

                $sca = WpScheduledCampaignArticle::create([
                    'wp_scheduled_campaign_id' => $campaign->id,
                    'article_id'               => $articleId,
                    'article_title_snapshot'   => $articleRow->name,
                    'article_body_snapshot'    => $articleRow->description,
                    'keyword'                  => $kwStore,
                    'url'                      => $urlStore,
                    'keyword_type'            => $kwType,
                    'url_type'                => $urlType,
                    'media'                   => $row['media'] ?? null,
                    'nofollow'                => !empty($row['nofollow']),
                ]);
                $articleMap[$i] = $sca->id;

                $affected = Article::where('id', $articleId)->whereNull('lock_at')->update(['lock_at' => now()]);
                if ($affected === 0) {
                    throw new \Exception("Article {$articleId} is already locked.");
                }
            }

            // Build list of (scheduled_date, quantity) and expand to slots
            $slots = [];
            foreach ($dateRows as $dr) {
                for ($k = 0; $k < $dr['quantity']; $k++) {
                    $slots[] = $dr['date'];
                }
            }
            if (count($slots) !== $postQty) {
                throw new \Exception('Slot count mismatch.');
            }

            $now  = now();
            $rows = [];
            for ($i = 0; $i < $postQty; $i++) {
                $scheduledDate = $slots[$i];

                // Decide exact schedule datetime (adding time to now avoids "Missed schedule" on WordPress):
                // - Future date → schedule that date at 10:00 AM
                // - Today or past → now + SCHEDULE_BUFFER_MINUTES so WP receives a future time (WP cron runs on visit)
                //   If buffer would cross to next day, cap to end of today
                $dateObj = Carbon::parse($scheduledDate)->startOfDay();
                if ($dateObj->isFuture()) {
                    $scheduledAt = $dateObj->copy()->setTime(10, 0, 0);
                } else {
                    $scheduledAt = $now->copy()->addMinutes(self::SCHEDULE_BUFFER_MINUTES);
                    if ($scheduledAt->format('Y-m-d') !== $now->format('Y-m-d')) {
                        $scheduledAt = $now->copy()->endOfDay();
                    }
                }

                $rows[] = [
                    'wp_scheduled_campaign_id'         => $campaign->id,
                    'wp_scheduled_campaign_domain_id'  => $domainMap[$i],
                    'wp_scheduled_campaign_article_id' => $articleMap[$i],
                    'scheduled_date'                   => $scheduledDate,
                    'scheduled_at'                    => $scheduledAt,
                    'status'                          => 'queued',
                    'attempt_count'                    => 0,
                    'created_at'                      => $now,
                    'updated_at'                      => $now,
                ];

                if (count($rows) >= 200) {
                    WpScheduledCampaignPost::insert($rows);
                    $rows = [];
                }
            }
            if (!empty($rows)) {
                WpScheduledCampaignPost::insert($rows);
            }
            return $campaign->id;
        });

        // Automatically dispatch jobs for all queued posts right after creation,
        // so WP scheduler runs without requiring a separate "Run queue" click.
        $this->dispatchQueuedPosts((int) $campaignId);

        return redirect()
            ->route('admin.wp.schedule.campaign.index')
            ->with('cus__success', 'WordPress scheduled campaign created. Posts will be sent to WordPress with their schedule dates.');
    }

    /**
     * Show edit form for a WP Scheduled campaign (campaign_no + distinct keyword/URL batches).
     */
    public function edit(string $id)
    {
        $campaign = WpScheduledCampaign::findOrFail($id);
        $this->authorizeCampaign($campaign);

        $articles = WpScheduledCampaignArticle::with('posts')
            ->where('wp_scheduled_campaign_id', $campaign->id)
            ->get();

        $batches = [];

        foreach ($articles as $ca) {
            $k = $ca->keyword === null ? '' : (string) $ca->keyword;
            $u = $ca->url === null ? '' : (string) $ca->url;
            $key = $k . "\n" . $u;

            if (!isset($batches[$key])) {
                $batches[$key] = [
                    'keyword'          => $k,
                    'url'              => $u,
                    'keyword_type'     => $ca->keyword_type,
                    'url_type'         => $ca->url_type,
                    'count'            => 0,
                    'article_ids'      => [],
                    'post_ids'         => [],
                    'representative_id'=> $ca->id,
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

        $orderedArticleIds = $this->orderedWpScheduledCampaignArticleIds($campaign);
        $postQuantity = count($orderedArticleIds);
        $multiLevelBoxes = $postQuantity > 0
            ? EditCampaignMultiLevelKeywordState::buildBoxes(
                $orderedArticleIds,
                fn (int $id) => WpScheduledCampaignArticle::where('wp_scheduled_campaign_id', $campaign->id)->find($id),
                fn ($ca) => EditCampaignMultiLevelKeywordState::payloadFromKeywordColumns($ca)
            )
            : [];
        $initialNofollow = false;
        if ($postQuantity > 0) {
            $firstCa = WpScheduledCampaignArticle::find($orderedArticleIds[0]);
            $initialNofollow = $firstCa && (bool) $firstCa->nofollow;
        }

        return view(
            'admin.campaigns.wp-scheduled.edit',
            compact('campaign', 'distinctBatches', 'postQuantity', 'multiLevelBoxes', 'initialNofollow')
        );
    }

    /**
     * Update basic campaign fields (currently: campaign_no).
     */
    public function update(Request $request, string $id)
    {
        $campaign = WpScheduledCampaign::find($id);
        if (!$campaign) {
            return back()->with('cus__error', 'The requested campaign is invalid');
        }
        $this->authorizeCampaign($campaign);

        $validated = $request->validate([
            'campaign_no' => 'required|string|max:191',
        ]);

        $campaignNo = $this->generateUniqueCampaignNo($validated['campaign_no'], (int) $campaign->id);

        $campaign->update([
            'campaign_no' => $campaignNo,
        ]);

        return back()
            ->with('cus__success', 'Successfully updated the campaign')
            ->with('edit_wp_schedule_campaign_tab', 'campaign');
    }

    /**
     * @return array<int, int>
     */
    private function orderedWpScheduledCampaignArticleIds(WpScheduledCampaign $campaign): array
    {
        return WpScheduledCampaignPost::query()
            ->where('wp_scheduled_campaign_id', $campaign->id)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->pluck('wp_scheduled_campaign_article_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int>|null  $onlyArticleIds
     * @return array{changed: bool, post_ids: array<int>}
     */
    private function applyWpScheduledCampaignKeywordBatch(
        WpScheduledCampaign $campaign,
        WpScheduledCampaignArticle $representative,
        $kwInput,
        $urlInput,
        ?array $onlyArticleIds = null
    ): array {
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
            $kwStore  = null;
            $urlStore = null;
        } elseif (count($pairsToStore) === 1 && ! $newIsJson) {
            $kwStore  = $pairsToStore[0][0];
            $urlStore = $pairsToStore[0][1];
        } else {
            $kwStore  = json_encode(array_column($pairsToStore, 0), JSON_UNESCAPED_UNICODE);
            $urlStore = json_encode(array_column($pairsToStore, 1), JSON_UNESCAPED_UNICODE);
        }

        $newKeywordType = $newIsJson ? 'json' : 'single';
        $newUrlType     = $newIsJson ? 'json' : 'single';

        if (
            $representative->keyword === $kwStore &&
            $representative->url === $urlStore &&
            ($representative->keyword_type ?? 'single') === $newKeywordType &&
            ($representative->url_type ?? 'single') === $newUrlType
        ) {
            return ['changed' => false, 'post_ids' => []];
        }

        if ($onlyArticleIds !== null) {
            $articleIds = WpScheduledCampaignArticle::where('wp_scheduled_campaign_id', $campaign->id)
                ->whereIn('id', array_map('intval', $onlyArticleIds))
                ->pluck('id')
                ->values()
                ->all();
        } else {
            $articleIds = WpScheduledCampaignArticle::where('wp_scheduled_campaign_id', $campaign->id)
                ->where('keyword', $representative->keyword)
                ->where('url', $representative->url)
                ->pluck('id')
                ->all();
        }

        if (count($articleIds) === 0) {
            return ['changed' => false, 'post_ids' => []];
        }

        WpScheduledCampaignArticle::whereIn('id', $articleIds)->update([
            'keyword'      => $kwStore,
            'url'          => $urlStore,
            'keyword_type' => $newKeywordType,
            'url_type'     => $newUrlType,
        ]);

        $ids = WpScheduledCampaignPost::whereIn('wp_scheduled_campaign_article_id', $articleIds)
            ->where('status', 'success')
            ->whereNotNull('remote_id')
            ->pluck('id')
            ->all();

        return ['changed' => true, 'post_ids' => $ids];
    }

    /**
     * Multi-level keywords: one JSON row per WP scheduled post (creation order).
     */
    public function multiLevelUpdateWpScheduleKeywords(Request $request, string $id)
    {
        $campaign = WpScheduledCampaign::find($id);
        if (! $campaign) {
            return redirect()
                ->route('admin.wp.schedule.campaign.index')
                ->with('cus__error', 'Campaign not found');
        }
        $this->authorizeCampaign($campaign);

        $request->validate([
            'keywordmethod'      => 'required|in:multiple',
            'keywordsDataHolder' => 'required|string',
        ]);

        $keywords = json_decode((string) $request->keywordsDataHolder, true);
        if (! is_array($keywords)) {
            return redirect()
                ->route('admin.wp.schedule.campaign.edit', $campaign->id)
                ->with('cus__error', 'Invalid keywords JSON.')
                ->with('edit_wp_schedule_campaign_tab', 'multi');
        }

        $orderedIds = $this->orderedWpScheduledCampaignArticleIds($campaign);
        if (count($orderedIds) === 0) {
            return redirect()
                ->route('admin.wp.schedule.campaign.edit', $campaign->id)
                ->with('cus__error', 'No campaign articles found.')
                ->with('edit_wp_schedule_campaign_tab', 'multi');
        }

        if (count($keywords) !== count($orderedIds)) {
            return redirect()
                ->route('admin.wp.schedule.campaign.edit', $campaign->id)
                ->with(
                    'cus__error',
                    'Keyword rows must be exactly '.count($orderedIds).' (your campaign post count).'
                )
                ->with('edit_wp_schedule_campaign_tab', 'multi');
        }

        $postIdsToUpdateOnRemote = [];

        try {
            DB::transaction(function () use ($campaign, $keywords, $orderedIds, &$postIdsToUpdateOnRemote) {
                foreach ($orderedIds as $i => $articleId) {
                    $ca = WpScheduledCampaignArticle::where('wp_scheduled_campaign_id', $campaign->id)->find($articleId);
                    if (! $ca) {
                        throw new \RuntimeException('Campaign article missing.');
                    }

                    $row = $keywords[$i] ?? [];
                    $res = $this->applyWpScheduledCampaignKeywordBatch(
                        $campaign,
                        $ca,
                        $row['keyword'] ?? null,
                        $row['url'] ?? null,
                        [$ca->id]
                    );

                    if (! empty($res['changed'])) {
                        $postIdsToUpdateOnRemote = array_merge($postIdsToUpdateOnRemote, $res['post_ids']);
                    }

                    $mediaVal = isset($row['media']) ? trim((string) $row['media']) : '';
                    $mediaVal = $mediaVal === '' ? null : $mediaVal;
                    $nofollow = ! empty($row['nofollow']);

                    WpScheduledCampaignArticle::where('id', $ca->id)->update([
                        'media'    => $mediaVal,
                        'nofollow' => $nofollow,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.wp.schedule.campaign.edit', $campaign->id)
                ->with('cus__error', $e->getMessage())
                ->with('edit_wp_schedule_campaign_tab', 'multi');
        }

        $postIdsToUpdateOnRemote = array_values(array_unique($postIdsToUpdateOnRemote));

        if (count($postIdsToUpdateOnRemote) > 0) {
            BulkUpdateWpScheduledPostsJob::dispatch($postIdsToUpdateOnRemote)->onQueue('wp_scheduled_campaign_bulk_updates');
        }

        $msg = count($postIdsToUpdateOnRemote) > 0
            ? 'Keywords updated. '.count($postIdsToUpdateOnRemote).' post(s) queued to update on remote.'
            : 'Keywords and media saved. No published posts to update on remote; future sends use new data.';

        return redirect()
            ->route('admin.wp.schedule.campaign.edit', $campaign->id)
            ->with('cus__success', $msg)
            ->with('edit_wp_schedule_campaign_tab', 'multi');
    }

    /**
     * Bulk update distinct keyword/URL batches for this campaign.
     * NOTE: This updates database only; existing WordPress posts are not edited automatically.
     */
    public function bulkUpdate(Request $request, string $id)
    {
        $campaign = WpScheduledCampaign::find($id);
        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }
        $this->authorizeCampaign($campaign);

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
            $representative = WpScheduledCampaignArticle::where('wp_scheduled_campaign_id', $campaign->id)->find($repId);
            if (!$representative) {
                continue;
            }

            $kwInput  = $batchKeywords[$index] ?? null;
            $urlInput = $batchUrls[$index] ?? null;

            $res = $this->applyWpScheduledCampaignKeywordBatch($campaign, $representative, $kwInput, $urlInput, null);

            if (! empty($res['changed'])) {
                $updatedBatches++;
                $postIdsToUpdateOnRemote = array_merge($postIdsToUpdateOnRemote, $res['post_ids']);
            }
        }

        $postIdsToUpdateOnRemote = array_values(array_unique($postIdsToUpdateOnRemote));

        if ($updatedBatches > 0 && count($postIdsToUpdateOnRemote) > 0) {
            BulkUpdateWpScheduledPostsJob::dispatch($postIdsToUpdateOnRemote)->onQueue('wp_scheduled_campaign_bulk_updates');
        }

        if ($updatedBatches === 0) {
            $msg = 'No keyword/URL changes were made.';
        } elseif (count($postIdsToUpdateOnRemote) > 0) {
            $msg = 'Batches updated in database. ' . count($postIdsToUpdateOnRemote);
        } else {
            $msg = 'Batches updated in database. No published posts to update on remote; queued/future posts will use new keywords/URLs when sent.';
        }

        return redirect()
            ->route('admin.wp.schedule.campaign.edit', $campaign->id)
            ->with($updatedBatches > 0 ? 'cus__success' : 'cus__error', $msg)
            ->with('edit_wp_schedule_campaign_tab', 'batch');
    }

    public function show(string $id)
    {
        $campaign = WpScheduledCampaign::with(['dateRows', 'domainCategory'])
            ->withCount(['posts', 'domains'])
            ->findOrFail($id);

        $this->authorizeCampaign($campaign);

        if (empty($campaign->report_token)) {
            $campaign->report_token = Str::random(64);
            $campaign->save();
        }

        $posts = $campaign->posts()
            ->with(['campaignArticle.article', 'campaignDomain.domain'])
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->paginate(50);

        return view('admin.campaigns.wp-scheduled.show', compact('campaign', 'posts'));
    }

    private function authorizeCampaign(WpScheduledCampaign $campaign): void
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin->isSuperAdmin() && (int) $campaign->admin_id !== (int) $admin->id) {
            abort(403);
        }
    }

    /**
     * Dispatch jobs to send queued posts to WordPress (with schedule date).
     */
    public function run(string $id)
    {
        $campaign = WpScheduledCampaign::findOrFail($id);
        $this->authorizeCampaign($campaign);

        $count = $this->dispatchQueuedPosts((int) $campaign->id);

        return back()->with('cus__success', $count . ' post(s) queued. Run: php artisan queue:work --queue=wp_scheduled_campaigns');
    }

    /**
     * Helper: dispatch jobs for all queued posts in a campaign.
     */
    private function dispatchQueuedPosts(int $campaignId): int
    {
        $campaign = WpScheduledCampaign::find($campaignId);
        if (!$campaign) {
            return 0;
        }

        $postIds = $campaign->posts()->where('status', 'queued')->pluck('id');

        foreach ($postIds as $postId) {
            PublishWpScheduledPostJob::dispatch($postId)->onQueue('wp_scheduled_campaigns');
        }

        if ($postIds->count() > 0) {
            $campaign->update([
                'started_at' => $campaign->started_at ?? now(),
                'status'     => 'running',
            ]);
        }

        return $postIds->count();
    }

    /**
     * Sync status from WordPress for all posts in this campaign (remote_id set).
     * Rate limited: same campaign can only be synced once per 10 minutes to avoid hitting server repeatedly.
     */
    public function syncCampaign(string $id)
    {
        $campaign = WpScheduledCampaign::findOrFail($id);
        $this->authorizeCampaign($campaign);

        $cacheKey = 'wp_schedule_sync_campaign_' . $campaign->id;
        if (Cache::has($cacheKey)) {
            return back()->with('cus__error', 'Sync was run recently. Please wait 10 minutes before syncing this campaign again.');
        }
        Cache::put($cacheKey, true, now()->addMinutes(10));

        $postIds = $campaign->posts()->whereNotNull('remote_id')->pluck('id');
        foreach ($postIds as $postId) {
            SyncWpScheduledPostStatusJob::dispatch($postId)->onQueue('wp_scheduled_sync');
        }

        return back()->with('cus__success', count($postIds) . ' post(s) queued for status sync.');
    }

    /**
     * Retry a single post: reset to queued and re-dispatch PublishWpScheduledPostJob (for failed/queued only).
     * Rate limited: same post can only be retried once per 3 minutes.
     */
    public function retryPost(int $postId)
    {
        $post = WpScheduledCampaignPost::with('campaign')->findOrFail($postId);
        $this->authorizeCampaign($post->campaign);

        $cacheKey = 'wp_schedule_retry_post_' . $post->id;
        if (Cache::has($cacheKey)) {
            return back()->with('cus__error', 'Retry was used recently for this post. Please wait 3 minutes.');
        }

        if (!in_array($post->status, ['queued', 'failed', 'publishing'], true)) {
            return back()->with('cus__error', 'Only failed or queued posts can be retried.');
        }

        Cache::put($cacheKey, true, now()->addMinutes(3));

        $post->update([
            'status'        => 'queued',
            'last_error'     => null,
            'next_retry_at'  => null,
            'locked_at'      => null,
            'lock_token'     => null,
        ]);

        PublishWpScheduledPostJob::dispatch($post->id)->onQueue('wp_scheduled_campaigns');

        return back()->with('cus__success', 'Post queued for retry.');
    }

    /**
     * Sync status from WordPress for a single post.
     * Rate limited: same post can only be synced once per 5 minutes to avoid hitting server repeatedly.
     */
    public function syncPost(int $postId)
    {
        $post = WpScheduledCampaignPost::with('campaign')->findOrFail($postId);
        $this->authorizeCampaign($post->campaign);

        if (empty($post->remote_id)) {
            return back()->with('cus__error', 'Post has no remote ID to sync.');
        }

        $cacheKey = 'wp_schedule_sync_post_' . $post->id;
        if (Cache::has($cacheKey)) {
            return back()->with('cus__error', 'Sync was run recently for this post. Please wait 5 minutes.');
        }
        Cache::put($cacheKey, true, now()->addMinutes(5));

        SyncWpScheduledPostStatusJob::dispatch($post->id)->onQueue('wp_scheduled_sync');

        return back()->with('cus__success', 'Status sync queued for this post.');
    }

    /**
     * Edit a single WP scheduled post (fetch content from remote WordPress).
     */
    public function editPost(int $postId)
    {
        $post = WpScheduledCampaignPost::with(['campaign', 'campaignDomain.domain'])->findOrFail($postId);
        $this->authorizeCampaign($post->campaign);

        if (empty($post->remote_id)) {
            return back()->with('cus__error', 'Post has no remote ID to edit.');
        }

        $domainName = optional($post->campaignDomain?->domain)->name;
        $apiKey     = optional($post->campaignDomain?->domain)->api_key;

        if (!$domainName || !$apiKey) {
            return back()->with('cus__error', 'Domain or API key is missing for this post.');
        }

        $url = "https://{$domainName}/wp-json/external/v1/posts/{$post->remote_id}?api_key={$apiKey}";

        try {
            $response = Http::withoutVerifying()->timeout(40)->get($url);
        } catch (\Throwable $e) {
            return back()->with('cus__error', 'Failed to fetch post content from remote site: ' . $e->getMessage());
        }

        if ($response->failed()) {
            return back()->with('cus__error', 'Failed to fetch post content from remote site.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            return back()->with('cus__error', 'Remote site did not return valid post data.');
        }
        $fetchedData = WordPressApiFetchedPost::normalizeForEditForm($data);

        return view('admin.campaigns.wp-scheduled.edit-post', [
            'campaignPost' => $post,
            'campaign'     => $post->campaign,
            'fetchedData'  => $fetchedData,
        ]);
    }

    /**
     * Update a single WP scheduled post on remote WordPress.
     */
    public function updatePost(Request $request, int $postId)
    {
        $post = WpScheduledCampaignPost::with(['campaign', 'campaignDomain.domain'])->find($postId);
        if (!$post) {
            return back()->with('cus__error', 'The requested post is invalid.');
        }

        $this->authorizeCampaign($post->campaign);

        $validated = $request->validate([
            'name'        => 'required|string',
            'description' => 'required|string',
        ]);

        $domainName = optional($post->campaignDomain?->domain)->name;
        $apiKey     = optional($post->campaignDomain?->domain)->api_key;

        if (!$domainName || !$apiKey || empty($post->remote_id)) {
            return back()->with('cus__error', 'Domain, API key or remote ID is missing for this post.');
        }

        $url = "https://{$domainName}/wp-json/external/v1/posts/update/{$post->remote_id}?api_key={$apiKey}";

        try {
            $response = Http::withoutVerifying()
                ->timeout(120)
                ->asJson()
                ->post($url, [
                    'title'   => $validated['name'],
                    'content' => $validated['description'],
                ]);
        } catch (\Throwable $e) {
            return back()->with('cus__error', 'Failed to update the post on the remote domain: ' . $e->getMessage());
        }

        if ($response->failed()) {
            return back()->with('cus__error', 'Failed to update the post on the remote domain.');
        }

        $post->update([
            'remote_response' => $response->json() ?? $post->remote_response,
            'published_at'    => $post->published_at ?? now(),
        ]);

        return redirect()
            ->route('admin.wp.schedule.campaign.show', $post->wp_scheduled_campaign_id)
            ->with('cus__success', "Successfully updated the scheduled post on {$domainName}.");
    }

    /**
     * Delete a single WP scheduled post (remote WordPress + local DB).
     */
    public function deletePost(int $postId)
    {
        $post = WpScheduledCampaignPost::with(['campaign', 'campaignArticle', 'campaignDomain.domain'])->find($postId);
        if (!$post) {
            return back()->with('cus__error', 'The requested scheduled post is invalid');
        }

        $campaign = $post->campaign;
        $this->authorizeCampaign($campaign);

        $postStatus = $post->status;

        DB::transaction(function () use ($post, $campaign, $postStatus) {
            $articleRow = $post->campaignArticle;
            $domainRow  = $post->campaignDomain;

            // Unlock article only if it was never sent (queued)
            if ($articleRow && $postStatus === 'queued') {
                $article = Article::find($articleRow->article_id);
                if ($article) {
                    $article->update([
                        'lock_at' => null,
                        'status'  => 0,
                    ]);
                }
            }

            // Adjust campaign counters
            if ($campaign->total_targets > 0) {
                $campaign->decrement('total_targets');
            }
            if ($postStatus === 'success' && $campaign->completed_targets > 0) {
                $campaign->decrement('completed_targets');
            }
            if ($postStatus === 'failed' && $campaign->failed_targets > 0) {
                $campaign->decrement('failed_targets');
            }

            // Delete remote WordPress post only if it was created successfully (publish/future)
            if ($postStatus === 'success') {
                $domain = $domainRow?->domain;
                if ($domain && $domain->api_key && $post->remote_id) {
                    $domainName = trim((string) $domain->name);
                    if (!preg_match('~^https?://~i', $domainName)) {
                        $domainName = 'https://' . $domainName;
                    }
                    $url = rtrim($domainName, '/') . '/wp-json/external/v1/posts/delete/' . $post->remote_id . '?api_key=' . urlencode($domain->api_key);

                    $response = Http::withoutVerifying()
                        ->timeout(120)
                        ->asJson()
                        ->delete($url);

                    if ($response->failed()) {
                        throw new \Exception('Remote WordPress delete failed: ' . $response->body());
                    }
                }
            }

            if ($articleRow) {
                $articleRow->delete();
            }

            if ($domainRow) {
                $domainRow->delete();
            }

            $post->delete();
        });

        return back()->with('cus__success', 'Successfully deleted the scheduled post and its associated data.');
    }

    /**
     * Public report page (token-protected). Route: admin.wp.schedule.campaign.report
     * Stats use display_status (Live/Queued/Failed) so the report reflects WordPress scheduler authentically.
     */
    public function report(string $campaign_no, string $token)
    {
        $campaign = WpScheduledCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        $posts = WpScheduledCampaignPost::with(['campaignDomain.domain', 'campaignArticle.article'])
            ->where('wp_scheduled_campaign_id', $campaign->id)
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get();

        $stats = (object) [
            'total'   => $posts->count(),
            'success' => $posts->where('display_status', 'Live')->count(),
            'queued'  => $posts->whereIn('display_status', ['Queued', 'Scheduled'])->count(),
            'missed'  => $posts->where('display_status', 'Missed schedule')->count(),
            'failed'  => $posts->where('display_status', 'Failed')->count(),
        ];

        $successRate = $stats->total > 0
            ? (int) round(($stats->success / $stats->total) * 100)
            : 0;

        return view('admin.campaigns.wp-scheduled.report', compact(
            'campaign',
            'stats',
            'successRate',
            'posts'
        ));
    }

    /**
     * Export report Excel (token-protected). Route: admin.wp.schedule.campaign.report.export
     */
    public function exportReport(string $campaign_no, string $token)
    {
        $campaign = WpScheduledCampaign::where('campaign_no', $campaign_no)
            ->where('report_token', $token)
            ->firstOrFail();

        $posts = WpScheduledCampaignPost::with(['campaignDomain.domain', 'campaignArticle.article'])
            ->where('wp_scheduled_campaign_id', $campaign->id)
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get();

        $headers = [
            'S.No',
            'Scheduled Date',
            'Domain',
            'Article',
            'Status',
            'Display (report)',
            'WP status',
            'Remote ID',
            'Remote URL',
            'Published At',
        ];

        $writer = SimpleExcelWriter::streamDownload(
            "wp-schedule-campaign-report-{$campaign_no}.xlsx"
        )->addHeader($headers);

        $sno = 1;
        foreach ($posts as $post) {
            $writer->addRow([
                'S.No'             => $sno++,
                'Scheduled Date'   => $post->scheduled_date?->format('d M Y') ?? '-',
                'Domain'           => optional($post->campaignDomain?->domain)->name ?? '-',
                'Article'          => \Illuminate\Support\Str::limit(optional($post->campaignArticle?->article)->name ?? '-', 80),
                'Status'           => ucfirst($post->status),
                'Display (report)' => $post->display_status,
                'WP status'        => $post->remote_status ?? '-',
                'Remote ID'        => $post->remote_id ?? '-',
                'Remote URL'       => $post->remote_url ?? '-',
                'Published At'     => $post->published_at?->format('d M Y H:i') ?? '-',
            ]);
        }

        return $writer->toBrowser();
    }

    /**
     * Queue deletion of a WP Scheduled campaign (remote WP posts + local DB cleanup).
     */
    public function destroy(string $id)
    {
        $campaign = WpScheduledCampaign::find($id);

        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }

        $this->authorizeCampaign($campaign);

        DeleteWpScheduledCampaignJob::dispatch($campaign->id)->onQueue('wp_scheduled_campaign_deletions');

        return redirect()
            ->route('admin.wp.schedule.campaign.index')
            ->with('cus__success', 'WP Scheduled campaign deletion queued.');
    }

    /**
     * Remove campaign data from this application only. Remote WordPress posts are not deleted.
     */
    public function purgeLocalOnly(string $id)
    {
        $campaign = WpScheduledCampaign::find($id);
        if (!$campaign) {
            return back()->with('cus__error', 'Campaign not found');
        }
        $this->authorizeCampaign($campaign);
        PurgeLocalCampaignDataService::purgeWpScheduledCampaign((int) $campaign->id);

        return redirect()
            ->route('admin.wp.schedule.campaign.index')
            ->with(
                'cus__success',
                'Campaign removed from this dashboard only. Remote posts were not deleted.'
            );
    }

    /**
     * Remove multiple WP Scheduled campaigns from the database only (no remote API calls).
     */
    public function bulkPurgeLocal(Request $request)
    {
        $ids = $this->validatedBulkCampaignIds($request);
        if ($ids === []) {
            return back()->with('cus__error', 'No campaigns selected.');
        }

        $allowed = $this->campaignIdsOwnedByCurrentAdmin($ids, WpScheduledCampaign::class);
        if ($allowed === []) {
            return back()->with('cus__error', 'No campaigns found or you do not have permission.');
        }

        foreach ($allowed as $id) {
            PurgeLocalCampaignDataService::purgeWpScheduledCampaign($id);
        }

        $n = count($allowed);

        return redirect()
            ->route('admin.wp.schedule.campaign.index')
            ->with('cus__success', $n . ' campaign(s) removed from this dashboard only. Remote posts were not deleted.');
    }
}
