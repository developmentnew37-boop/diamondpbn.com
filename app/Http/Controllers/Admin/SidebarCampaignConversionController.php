<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AppliesSuperAdminCampaignOwnerFilter;
use App\Http\Controllers\Admin\Concerns\AuthorizesAdminCampaign;
use App\Http\Controllers\Controller;
use App\Jobs\ApplyConvertedSidebarScheduleJob;
use App\Jobs\BulkRetrySidebarCampaignTasksJob;
use App\Jobs\DraftConvertedLiveSidebarTasksJob;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Models\Admin\SidebarCampaign;
use App\Services\CampaignConversionPreflightService;
use App\Services\CampaignReportUrlResolver;
use App\Services\ScheduleSidebarCampaignTargetCounterService;
use App\Services\SidebarConversionEligibilityService;
use App\Services\SidebarLiveToDripfeedConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SidebarCampaignConversionController extends Controller
{
    use AppliesSuperAdminCampaignOwnerFilter;
    use AuthorizesAdminCampaign;

    public function __construct(
        private readonly SidebarConversionEligibilityService $eligibility,
        private readonly SidebarLiveToDripfeedConversionService $conversionService,
        private readonly CampaignConversionPreflightService $preflightService,
    ) {
        $this->middleware('can.create.campaigns');
    }

    public function convertedIndex(Request $request, ScheduleSidebarCampaignTargetCounterService $counters)
    {
        $request->validate([
            'search' => 'nullable|string|max:150',
            'filter_user' => 'nullable|string|max:20',
        ]);

        $admin = Auth::guard('admin')->user();
        $limit = (int) config('campaign.pagination.default_limit', 25);

        $query = ScheduleSidebarCampaign::query()
            ->with([
                'domainCategory:id,name',
                'sourceSidebarCampaign:id,campaign_no,domain_category_id',
                'sourceSidebarCampaign.domainCategory:id,name',
            ])
            ->withCount([
                'tasks as live_tasks_total' => fn ($q) => $q->where('is_converted_live', true),
                'tasks as live_tasks_success' => fn ($q) => $q->where('is_converted_live', true)->where('status', 'success'),
                'tasks as live_tasks_failed' => fn ($q) => $q->where('is_converted_live', true)->where('status', 'failed'),
            ])
            ->whereNotNull('converted_from_sidebar_campaign_id')
            ->orderByDesc('id');

        $statsQuery = ScheduleSidebarCampaign::query()
            ->whereNotNull('converted_from_sidebar_campaign_id');

        $ownerData = $this->scopeCampaignQueryForOwner($query, $request, $admin);
        $this->scopeCampaignQueryForOwner($statsQuery, $request, $admin);

        if ($request->filled('search')) {
            $search = '%'.trim($request->search).'%';
            $query->where(function ($q) use ($search) {
                $q->where('campaign_no', 'like', $search)
                    ->orWhereHas('sourceSidebarCampaign', fn ($sq) => $sq->where('campaign_no', 'like', $search));
            });
        }

        $stats = $counters->convertedRegistryStats($statsQuery);

        $campaigns = $query
            ->paginate($limit)
            ->withQueryString();

        $campaigns->getCollection()->each(function (ScheduleSidebarCampaign $campaign) use ($counters) {
            $storedFailed = (int) $campaign->failed_targets;
            $actualFailed = (int) $campaign->live_tasks_failed;
            $storedCompleted = (int) $campaign->completed_targets;
            $actualCompleted = (int) $campaign->live_tasks_success;

            if ($storedFailed !== $actualFailed
                || $storedCompleted !== $actualCompleted
                || (int) $campaign->live_tasks_total !== (int) $campaign->total_targets) {
                $counters->syncCampaignFromTasks($campaign);
                $campaign->refresh();
            }
        });

        $offset = ($campaigns->currentPage() - 1) * $campaigns->perPage();

        return view(
            'admin.campaigns.convert-sidebar.converted-index',
            array_merge(compact('campaigns', 'offset', 'stats'), $ownerData)
        );
    }

    public function wizardStep1(Request $request)
    {
        $preselectedId = $request->integer('campaign_id');
        $preselectedCampaign = null;
        if ($preselectedId > 0) {
            $preselectedCampaign = SidebarCampaign::query()
                ->select('id', 'campaign_no')
                ->find($preselectedId);
        }

        return view('admin.campaigns.convert-sidebar.step1-select', [
            'preselectedId' => $preselectedId > 0 ? $preselectedId : null,
            'preselectedCampaign' => $preselectedCampaign,
        ]);
    }

    public function wizardStep2(int $campaignId)
    {
        $campaign = SidebarCampaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        $summary = $this->eligibility->summarize($campaign);
        if ($summary['can_skip_recover']) {
            return redirect()->route('admin.convert.sidebar.step3', $campaignId);
        }

        return view('admin.campaigns.convert-sidebar.step2-recover', [
            'campaign' => $campaign,
            'summary' => $summary,
        ]);
    }

    public function wizardStep3(int $campaignId, Request $request)
    {
        $campaign = SidebarCampaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        $summary = $this->eligibility->summarize($campaign);
        if ($summary['in_progress'] > 0) {
            return redirect()
                ->route('admin.convert.sidebar.step2', $campaignId)
                ->with('cus__error', 'Tasks are still publishing. Wait for the queue to finish.');
        }

        $mode = $request->string('mode')->toString();
        if (! in_array($mode, ['partial', 'full'], true)) {
            $mode = $summary['failed'] > 0 ? 'partial' : 'full';
        }

        return view('admin.campaigns.convert-sidebar.step3-schedule', [
            'campaign' => $campaign,
            'summary' => $summary,
            'conversionMode' => $mode,
        ]);
    }

    public function wizardStep4(int $campaignId, Request $request)
    {
        $campaign = SidebarCampaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        $sessionKey = 'convert_sidebar_step4_'.$campaignId;

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'conversion_mode' => 'required|in:partial,full',
                'date_quantities' => 'required|json',
                'schedule_from_date' => 'nullable|date',
                'schedule_to_date' => 'nullable|date',
            ]);

            $dateQuantities = json_decode($validated['date_quantities'], true);
            if (! is_array($dateQuantities)) {
                return redirect()
                    ->route('admin.convert.sidebar.step3', ['campaign' => $campaignId])
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

            return redirect()->route('admin.convert.sidebar.step4', $campaignId);
        }

        $payload = session($sessionKey);
        if (! is_array($payload) || empty($payload['date_quantities'])) {
            return redirect()
                ->route('admin.convert.sidebar.step3', ['campaign' => $campaignId])
                ->with('cus__error', 'Complete the schedule step before confirming conversion.');
        }

        $summary = $this->eligibility->summarize($campaign);

        return view('admin.campaigns.convert-sidebar.step4-confirm', [
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
            $cacheKey = 'convert_sidebar_campaign_search_'.$admin->id;
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json($cached);
            }
        }

        $query = $this->eligibility->campaignQueryForAdmin((int) $admin->id, $isSuper)
            ->withCount([
                'tasks as success_tasks_count' => fn ($b) => $b->where('status', 'success')->whereNotNull('remote_id'),
                'tasks as failed_tasks_count' => fn ($b) => $b->where('status', 'failed'),
            ])
            ->orderByDesc('id');

        if (strlen($q) >= 2) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('campaign_no', 'like', $like);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (SidebarCampaign $campaign) {
            $summary = $this->eligibility->summarize($campaign);

            return [
                'id' => $campaign->id,
                'campaign_no' => $campaign->campaign_no,
                'status' => $campaign->status,
                'created_at' => optional($campaign->created_at)->toDateTimeString(),
                'convertible' => $summary['convertible'],
                'failed' => $summary['failed'],
                'in_progress' => $summary['in_progress'],
                'disabled' => $summary['convertible'] < 1 || $campaign->converted_to_schedule_sidebar_campaign_id !== null,
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

        if (($resolved['report_family'] ?? '') !== 'sidebar/campaign/report') {
            return response()->json([
                'ok' => false,
                'message' => 'Only live sidebar campaign report links can be converted. This link is for: '.$resolved['type'].'.',
            ], 422);
        }

        $campaign = SidebarCampaign::query()->find($resolved['campaign_id']);
        if ($campaign === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Campaign not found.',
            ], 404);
        }

        $this->authorizeCampaignAccess($campaign);
        $summary = $this->eligibility->summarize($campaign);

        $alreadyConverted = filled($campaign->converted_to_schedule_sidebar_campaign_id);
        $disabled = $summary['convertible'] < 1 || $alreadyConverted;

        $message = null;
        if ($alreadyConverted) {
            $message = 'This campaign was already converted to scheduled sidebar.';
        } elseif ($summary['convertible'] < 1) {
            $message = 'No published live blogroll tasks with remote IDs are available to convert yet.';
        } elseif ($summary['in_progress'] > 0) {
            $message = 'Some tasks are still publishing. You can continue; step 2 will wait until they finish.';
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
        $campaign = SidebarCampaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        return response()->json($this->eligibility->summarize($campaign));
    }

    public function retryFailed(int $campaignId): JsonResponse
    {
        $campaign = SidebarCampaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        BulkRetrySidebarCampaignTasksJob::dispatch([(int) $campaign->id])->onQueue('bulk_retry_sidebar_campaigns');

        return response()->json(['queued' => true]);
    }

    public function preflightForCampaign(int $campaignId): JsonResponse
    {
        $campaign = SidebarCampaign::findOrFail($campaignId);
        $this->authorizeCampaignAccess($campaign);

        $domainIds = $campaign->domains()->pluck('domain_id')->all();
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
            'campaign_id' => 'required|integer|exists:sidebar_campaigns,id',
            'campaign_no' => 'required|string|max:191',
            'conversion_mode' => 'required|in:partial,full',
            'date_quantities' => 'required|json',
            'acknowledge_risk' => 'nullable|boolean',
        ]);

        $campaign = SidebarCampaign::findOrFail($validated['campaign_id']);
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

        $admin = Auth::guard('admin')->user();

        $schedule = $this->conversionService->convert(
            $campaign,
            (int) $admin->id,
            $validated['campaign_no'],
            $normalized,
            $validated['conversion_mode'],
            (bool) ($validated['acknowledge_risk'] ?? false),
        );

        session()->forget('convert_sidebar_step4_'.$campaign->id);

        return redirect()
            ->route('admin.schedule.sidebar.campaign.show', $schedule->id)
            ->with('cus__success', 'Conversion started. Draft and publish jobs are running in the background.');
    }

    public function conversionStatus(int $scheduleCampaignId): JsonResponse
    {
        $campaign = ScheduleSidebarCampaign::findOrFail($scheduleCampaignId);
        $this->authorizeCampaignAccess($campaign);

        $tasks = ScheduleSidebarCampaignTask::query()
            ->where('schedule_sidebar_campaign_id', $campaign->id)
            ->where('is_converted_live', true);

        return response()->json([
            'pipeline_status' => $campaign->conversion_pipeline_status,
            'drafted' => (clone $tasks)->where('conversion_phase', 'drafted')->count(),
            'published' => (clone $tasks)->where('conversion_phase', 'published')->count(),
            'failed' => (clone $tasks)->where('conversion_phase', 'failed')->count(),
            'pending_draft' => (clone $tasks)->where('conversion_phase', 'pending_draft')->count(),
        ]);
    }

    public function retryConversionTask(int $taskId)
    {
        $task = ScheduleSidebarCampaignTask::with('campaign')->findOrFail($taskId);
        $this->authorizeCampaignAccess($task->campaign);

        if (! $task->is_converted_live) {
            return back()->with('cus__error', 'This is not a converted live task.');
        }

        if ($task->conversion_phase === 'pending_draft' || $task->conversion_phase === 'failed') {
            $task->update([
                'conversion_phase' => 'pending_draft',
                'status' => 'queued',
                'remote_status' => null,
                'last_error' => null,
                'last_conversion_error' => null,
            ]);
            DraftConvertedLiveSidebarTasksJob::dispatch([$task->id], (int) $task->schedule_sidebar_campaign_id)
                ->onQueue(DraftConvertedLiveSidebarTasksJob::QUEUE);

            return back()->with('cus__success', 'Conversion retry queued. The task will be drafted on WordPress; it publishes automatically on its schedule date.');
        }

        $task->update([
            'conversion_phase' => 'drafted',
            'status' => 'queued',
            'last_error' => null,
            'last_conversion_error' => null,
        ]);
        ApplyConvertedSidebarScheduleJob::dispatch($task->id)->onQueue(ApplyConvertedSidebarScheduleJob::QUEUE);

        return back()->with('cus__success', 'Publish retry queued for this task.');
    }

    public function bulkRetryConversionFailed(int $scheduleCampaignId)
    {
        $campaign = ScheduleSidebarCampaign::findOrFail($scheduleCampaignId);
        $this->authorizeCampaignAccess($campaign);

        $failed = ScheduleSidebarCampaignTask::query()
            ->where('schedule_sidebar_campaign_id', $campaign->id)
            ->where('is_converted_live', true)
            ->where('conversion_phase', 'failed')
            ->pluck('id')
            ->all();

        foreach ($failed as $id) {
            $task = ScheduleSidebarCampaignTask::find($id);
            if (! $task) {
                continue;
            }
            $task->update([
                'conversion_phase' => 'pending_draft',
                'status' => 'queued',
                'remote_status' => null,
                'last_error' => null,
                'last_conversion_error' => null,
            ]);
        }

        if ($failed !== []) {
            DraftConvertedLiveSidebarTasksJob::dispatch($failed, (int) $campaign->id)
                ->onQueue(DraftConvertedLiveSidebarTasksJob::QUEUE);
        }

        return back()->with('cus__success', 'Retry queued for '.count($failed).' task(s).');
    }
}
