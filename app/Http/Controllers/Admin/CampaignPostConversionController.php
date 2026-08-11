<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AppliesSuperAdminCampaignOwnerFilter;
use App\Http\Controllers\Admin\Concerns\AuthorizesAdminCampaign;
use App\Http\Controllers\Controller;
use App\Jobs\ApplyConvertedPostScheduleJob;
use App\Jobs\BulkRetryCampaignPostsJob;
use App\Jobs\DraftConvertedLivePostsJob;
use App\Models\Admin\Campaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignPost;
use App\Services\CampaignConversionEligibilityService;
use App\Services\CampaignConversionPreflightService;
use App\Services\CampaignLiveToDripfeedConversionService;
use App\Services\CampaignReportUrlResolver;
use App\Services\ScheduleCampaignTargetCounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CampaignPostConversionController extends Controller
{
    use AppliesSuperAdminCampaignOwnerFilter;
    use AuthorizesAdminCampaign;

    public function __construct(
        private readonly CampaignConversionEligibilityService $eligibility,
        private readonly CampaignLiveToDripfeedConversionService $conversionService,
        private readonly CampaignConversionPreflightService $preflightService,
    ) {
        $this->middleware('can.create.campaigns');
    }

    public function convertedIndex(Request $request, ScheduleCampaignTargetCounterService $counters)
    {
        $request->validate([
            'search' => 'nullable|string|max:150',
            'filter_user' => 'nullable|string|max:20',
        ]);

        $admin = Auth::guard('admin')->user();
        $limit = (int) config('campaign.pagination.default_limit', 25);

        $query = ScheduleCampaign::query()
            ->with([
                'domainCategory:id,name',
                'sourceCampaign:id,campaign_no,domain_category_id',
                'sourceCampaign.domainCategory:id,name',
            ])
            ->withCount([
                'posts as live_posts_total' => fn ($q) => $q->where('is_converted_live', true),
                'posts as live_posts_success' => fn ($q) => $q->where('is_converted_live', true)->where('status', 'success'),
                'posts as live_posts_failed' => fn ($q) => $q->where('is_converted_live', true)->where('status', 'failed'),
            ])
            ->whereNotNull('converted_from_campaign_id')
            ->orderByDesc('id');

        $statsQuery = ScheduleCampaign::query()
            ->whereNotNull('converted_from_campaign_id');

        $ownerData = $this->scopeCampaignQueryForOwner($query, $request, $admin);
        $this->scopeCampaignQueryForOwner($statsQuery, $request, $admin);

        if ($request->filled('search')) {
            $search = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($search) {
                $q->where('campaign_no', 'like', $search)
                    ->orWhereHas('sourceCampaign', fn ($sq) => $sq->where('campaign_no', 'like', $search));
            });
        }

        $stats = $counters->convertedRegistryStats($statsQuery);

        $campaigns = $query
            ->paginate($limit)
            ->withQueryString();

        $campaigns->getCollection()->each(function (ScheduleCampaign $campaign) use ($counters) {
            $storedFailed = (int) $campaign->failed_targets;
            $actualFailed = (int) $campaign->live_posts_failed;
            $storedCompleted = (int) $campaign->completed_targets;
            $actualCompleted = (int) $campaign->live_posts_success;

            if ($storedFailed !== $actualFailed
                || $storedCompleted !== $actualCompleted
                || (int) $campaign->live_posts_total !== (int) $campaign->total_targets) {
                $counters->syncCampaignFromPosts($campaign);
                $campaign->refresh();
            }
        });

        $offset = ($campaigns->currentPage() - 1) * $campaigns->perPage();

        return view(
            'admin.campaigns.convert.converted-index',
            array_merge(compact('campaigns', 'offset', 'stats'), $ownerData)
        );
    }

    public function wizardStep1(Request $request)
    {
        $preselectedId = $request->integer('campaign_id');
        $preselectedCampaign = null;
        if ($preselectedId > 0) {
            $preselectedCampaign = Campaign::query()
                ->select('id', 'campaign_no')
                ->find($preselectedId);
        }

        return view('admin.campaigns.convert.step1-select', [
            'preselectedId' => $preselectedId > 0 ? $preselectedId : null,
            'preselectedCampaign' => $preselectedCampaign,
        ]);
    }

    public function wizardStep2(int $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        $summary = $this->eligibility->summarize($campaign);
        if ($summary['can_skip_recover']) {
            return redirect()->route('admin.convert.post.step3', $campaignId);
        }

        return view('admin.campaigns.convert.step2-recover', [
            'campaign' => $campaign,
            'summary' => $summary,
        ]);
    }

    public function wizardStep3(int $campaignId, Request $request)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        $summary = $this->eligibility->summarize($campaign);
        if ($summary['in_progress'] > 0) {
            return redirect()
                ->route('admin.convert.post.step2', $campaignId)
                ->with('cus__error', 'Posts are still publishing. Wait for the queue to finish.');
        }

        $mode = $request->string('mode')->toString();
        if (! in_array($mode, ['partial', 'exchange', 'full'], true)) {
            $mode = $summary['failed'] > 0 ? 'partial' : 'full';
        }

        return view('admin.campaigns.convert.step3-schedule', [
            'campaign' => $campaign,
            'summary' => $summary,
            'conversionMode' => $mode,
        ]);
    }

    public function wizardStep4(int $campaignId, Request $request)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        $sessionKey = 'convert_post_step4_'.$campaignId;

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'conversion_mode' => 'required|in:partial,exchange,full',
                'date_quantities' => 'required|json',
                'schedule_from_date' => 'nullable|date',
                'schedule_to_date' => 'nullable|date',
            ]);

            $dateQuantities = json_decode($validated['date_quantities'], true);
            if (! is_array($dateQuantities)) {
                return redirect()
                    ->route('admin.convert.post.step3', ['campaign' => $campaignId])
                    ->with('cus__error', 'Invalid date quantities.');
            }

            session([
                $sessionKey => [
                    'conversion_mode' => $validated['conversion_mode'],
                    'date_quantities' => $dateQuantities,
                    'schedule_from_date' => $validated['schedule_from_date'] ?? null,
                    'schedule_to_date' => $validated['schedule_to_date'] ?? null,
                ],
            ]);

            return redirect()->route('admin.convert.post.step4', $campaignId);
        }

        $payload = session($sessionKey);
        if (! is_array($payload) || empty($payload['date_quantities'])) {
            return redirect()
                ->route('admin.convert.post.step3', ['campaign' => $campaignId])
                ->with('cus__error', 'Complete the schedule step before confirming conversion.');
        }

        $summary = $this->eligibility->summarize($campaign);

        return view('admin.campaigns.convert.step4-confirm', [
            'campaign' => $campaign,
            'summary' => $summary,
            'conversionMode' => $payload['conversion_mode'],
            'dateQuantities' => $payload['date_quantities'],
            'scheduleFrom' => $payload['schedule_from_date'] ?? null,
            'scheduleTo' => $payload['schedule_to_date'] ?? null,
        ]);
    }

    public function searchCampaigns(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $isSuper = $admin && method_exists($admin, 'isSuperAdmin') && $admin->isSuperAdmin();

        $q = trim($request->string('q')->toString());
        $page = max(1, $request->integer('page', 1));
        $perPage = min(20, max(10, $request->integer('per_page', 15)));

        $cacheKey = null;
        if ($q === '' && $page === 1 && $admin) {
            $cacheKey = 'convert_campaign_search_'.$admin->id;
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json($cached);
            }
        }

        $query = $this->eligibility->campaignQueryForAdmin((int) $admin->id, $isSuper)
            ->withCount([
                'campaignPosts as success_posts_count' => fn ($b) => $b->where('status', 'success')->whereNotNull('remote_id'),
                'campaignPosts as failed_posts_count' => fn ($b) => $b->where('status', 'failed'),
            ])
            ->orderByDesc('id');

        if (strlen($q) >= 2) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('campaign_no', 'like', $like);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (Campaign $campaign) {
            $summary = $this->eligibility->summarize($campaign);

            return [
                'id' => $campaign->id,
                'campaign_no' => $campaign->campaign_no,
                'status' => $campaign->status,
                'created_at' => optional($campaign->created_at)->toDateTimeString(),
                'convertible' => $summary['convertible'],
                'failed' => $summary['failed'],
                'in_progress' => $summary['in_progress'],
                'disabled' => $summary['convertible'] < 1 || $campaign->converted_to_schedule_campaign_id !== null,
            ];
        });

        $payload = [
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $payload, 60);
        }

        return response()->json($payload);
    }

    public function lookupByReportUrl(Request $request, CampaignReportUrlResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'report_url' => ['required', 'string', 'max:2048'],
        ]);

        $admin = Auth::guard('admin')->user();
        $resolved = $resolver->resolve($validated['report_url'], $request, $admin);

        if ($resolved === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Campaign not found or you do not have permission to view it.',
            ], 404);
        }

        if (($resolved['report_family'] ?? '') !== 'campaign/report') {
            return response()->json([
                'ok' => false,
                'message' => 'Only live PBN post campaign report links can be converted. This link is for: '.$resolved['type'].'.',
            ], 422);
        }

        $campaign = Campaign::query()->find($resolved['campaign_id']);
        if ($campaign === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Campaign not found.',
            ], 404);
        }

        $this->authorizeCampaignAccess($campaign);
        $summary = $this->eligibility->summarize($campaign);

        $alreadyConverted = filled($campaign->converted_to_schedule_campaign_id);
        $disabled = $summary['convertible'] < 1 || $alreadyConverted;

        $message = null;
        if ($alreadyConverted) {
            $message = 'This campaign was already converted to dripfeed.';
        } elseif ($summary['convertible'] < 1) {
            $message = 'No published live posts with remote IDs are available to convert yet.';
        } elseif ($summary['in_progress'] > 0) {
            $message = 'Some posts are still publishing. You can continue; step 2 will wait until they finish.';
        }

        return response()->json([
            'ok' => true,
            'campaign' => [
                'id' => $campaign->id,
                'campaign_no' => $campaign->campaign_no,
                'status' => $campaign->status,
                'type' => $resolved['type'],
                'convertible' => $summary['convertible'],
                'failed' => $summary['failed'],
                'in_progress' => $summary['in_progress'],
                'disabled' => $disabled,
                'message' => $message,
            ],
        ]);
    }

    public function eligibility(int $campaignId): JsonResponse
    {
        $campaign = Campaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        return response()->json($this->eligibility->summarize($campaign));
    }

    public function retryFailed(int $campaignId): JsonResponse
    {
        $campaign = Campaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        BulkRetryCampaignPostsJob::dispatch([(int) $campaign->id])->onQueue('bulk_retry');

        return response()->json(['queued' => true]);
    }

    public function preflight(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
        ]);

        $campaign = Campaign::findOrFail($validated['campaign_id']);
        $this->authorizeCampaignAccess($campaign);

        $domainIds = $campaign->campaignDomains()->pluck('domain_id')->all();
        $rows = $this->preflightService->checkDomains($domainIds);

        return response()->json([
            'domains' => $rows,
            'offline_domain_count' => collect($rows)->where('ok', false)->count(),
            'warn_only' => true,
        ]);
    }

    public function preflightForCampaign(int $campaignId): JsonResponse
    {
        $campaign = Campaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        $domainIds = $campaign->campaignDomains()->pluck('domain_id')->all();
        $rows = $this->preflightService->checkDomains($domainIds);

        return response()->json([
            'domains' => $rows,
            'offline_domain_count' => collect($rows)->where('ok', false)->count(),
            'warn_only' => true,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
            'campaign_no' => 'required|string|max:191',
            'conversion_mode' => 'required|in:partial,exchange,full',
            'date_quantities' => 'required|json',
            'acknowledge_risk' => 'nullable|boolean',
            'exchange_mappings' => 'nullable|json',
        ]);

        $campaign = Campaign::findOrFail($validated['campaign_id']);
        $this->authorizeCampaignAccess($campaign);

        $dateQuantities = json_decode($validated['date_quantities'], true);
        if (! is_array($dateQuantities)) {
            return back()->with('cus__error', 'Invalid date quantities.');
        }

        $normalized = [];
        foreach ($dateQuantities as $row) {
            if (! isset($row['date'], $row['quantity'])) {
                continue;
            }
            $normalized[] = [
                'date' => $row['date'],
                'quantity' => (int) $row['quantity'],
            ];
        }

        $exchange = [];
        if (! empty($validated['exchange_mappings'])) {
            $decoded = json_decode($validated['exchange_mappings'], true);
            if (is_array($decoded)) {
                $exchange = $decoded;
            }
        }

        $admin = Auth::guard('admin')->user();

        $schedule = $this->conversionService->convert(
            $campaign,
            (int) $admin->id,
            $validated['campaign_no'],
            $normalized,
            $validated['conversion_mode'],
            $exchange,
            (bool) ($validated['acknowledge_risk'] ?? false),
        );

        session()->forget('convert_post_step4_'.$campaign->id);

        return redirect()
            ->route('admin.schedule.campaign.show', $schedule->id)
            ->with('cus__success', 'Conversion started. Draft and publish jobs are running in the background.');
    }

    public function conversionStatus(int $scheduleCampaignId): JsonResponse
    {
        $campaign = ScheduleCampaign::findOrFail($scheduleCampaignId);
        $this->authorizeCampaignAccess($campaign);

        $posts = ScheduleCampaignPost::query()
            ->where('schedule_campaign_id', $campaign->id)
            ->where('is_converted_live', true);

        return response()->json([
            'pipeline_status' => $campaign->conversion_pipeline_status,
            'drafted' => (clone $posts)->where('conversion_phase', 'drafted')->count(),
            'published' => (clone $posts)->where('conversion_phase', 'published')->count(),
            'failed' => (clone $posts)->where('conversion_phase', 'failed')->count(),
            'pending_draft' => (clone $posts)->where('conversion_phase', 'pending_draft')->count(),
        ]);
    }

    public function retryConversionPost(int $postId)
    {
        $post = ScheduleCampaignPost::with('campaign')->findOrFail($postId);
        $this->authorizeCampaignAccess($post->campaign);

        if (! $post->is_converted_live) {
            return back()->with('cus__error', 'This is not a converted live post.');
        }

        if ($post->conversion_phase === 'pending_draft' || $post->conversion_phase === 'failed') {
            $post->update([
                'conversion_phase' => 'pending_draft',
                'status' => 'queued',
                'last_error' => null,
                'last_conversion_error' => null,
            ]);
            DraftConvertedLivePostsJob::dispatch([$post->id], (int) $post->schedule_campaign_id)
                ->onQueue(DraftConvertedLivePostsJob::QUEUE);
        } else {
            $post->update([
                'conversion_phase' => 'drafted',
                'status' => 'queued',
                'last_error' => null,
                'last_conversion_error' => null,
            ]);
            ApplyConvertedPostScheduleJob::dispatch($post->id)->onQueue(ApplyConvertedPostScheduleJob::QUEUE);
        }

        return back()->with('cus__success', 'Conversion retry queued.');
    }

    public function bulkRetryConversionFailed(int $scheduleCampaignId)
    {
        $campaign = ScheduleCampaign::findOrFail($scheduleCampaignId);
        $this->authorizeCampaignAccess($campaign);

        $failed = ScheduleCampaignPost::query()
            ->where('schedule_campaign_id', $campaign->id)
            ->where('is_converted_live', true)
            ->where('conversion_phase', 'failed')
            ->pluck('id')
            ->all();

        foreach ($failed as $id) {
            $post = ScheduleCampaignPost::find($id);
            if (! $post) {
                continue;
            }
            $post->update(['conversion_phase' => 'pending_draft', 'status' => 'queued']);
        }

        if ($failed !== []) {
            DraftConvertedLivePostsJob::dispatch($failed, (int) $campaign->id)
                ->onQueue(DraftConvertedLivePostsJob::QUEUE);
        }

        return back()->with('cus__success', 'Retry queued for '.count($failed).' post(s).');
    }
}
