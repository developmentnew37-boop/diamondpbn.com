<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplaceCampaignDomainRequest;
use App\Models\Admin\HiddenLinksCampaignDomainReplacement;
use App\Models\Admin\HiddenLinksCampaignTasks;
use App\Models\Admin\ScheduleCampaignDomainReplacement;
use App\Models\Admin\ScheduleCampaignPost;
use App\Models\Admin\ScheduleSidebarCampaignDomainReplacement;
use App\Models\Admin\ScheduleSidebarCampaignTask;
use App\Models\Admin\SidebarCampaignDomainReplacement;
use App\Models\Admin\SidebarCampaignTask;
use App\Services\LiveTaskDomainReplacement\LiveTaskDomainReplacementService;
use App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class LiveTaskDomainReplacementController extends Controller
{
    public function __construct(
        private readonly LiveTaskDomainReplacementService $replacementService,
    ) {}

    public function createSidebar(Request $request, SidebarCampaignTask $task)
    {
        return $this->createForm($request, LiveTaskReplacementProfile::sidebar(), $task, 'Sidebar link');
    }

    public function storeSidebar(ReplaceCampaignDomainRequest $request, SidebarCampaignTask $task)
    {
        return $this->storeReplacement(
            $request,
            LiveTaskReplacementProfile::sidebar(),
            $task,
            SidebarCampaignDomainReplacement::class,
            'sidebar task'
        );
    }

    public function createHiddenLinks(Request $request, HiddenLinksCampaignTasks $task)
    {
        return $this->createForm($request, LiveTaskReplacementProfile::hiddenLinks(), $task, 'Hidden link');
    }

    public function storeHiddenLinks(ReplaceCampaignDomainRequest $request, HiddenLinksCampaignTasks $task)
    {
        return $this->storeReplacement(
            $request,
            LiveTaskReplacementProfile::hiddenLinks(),
            $task,
            HiddenLinksCampaignDomainReplacement::class,
            'hidden link task'
        );
    }

    public function createSchedulePost(Request $request, ScheduleCampaignPost $post)
    {
        $profile = $post->is_converted_live
            ? LiveTaskReplacementProfile::convertedSchedulePost()
            : LiveTaskReplacementProfile::schedulePost();

        return $this->createForm(
            $request,
            $profile,
            $post,
            $post->is_converted_live ? 'Converted post' : 'Scheduled post'
        );
    }

    public function storeSchedulePost(ReplaceCampaignDomainRequest $request, ScheduleCampaignPost $post)
    {
        $profile = $post->is_converted_live
            ? LiveTaskReplacementProfile::convertedSchedulePost()
            : LiveTaskReplacementProfile::schedulePost();

        return $this->storeReplacement(
            $request,
            $profile,
            $post,
            ScheduleCampaignDomainReplacement::class,
            $post->is_converted_live ? 'converted post' : 'scheduled post'
        );
    }

    public function createScheduleSidebar(Request $request, ScheduleSidebarCampaignTask $task)
    {
        $profile = $task->is_converted_live
            ? LiveTaskReplacementProfile::convertedScheduleSidebar()
            : LiveTaskReplacementProfile::scheduleSidebar();

        return $this->createForm(
            $request,
            $profile,
            $task,
            $task->is_converted_live ? 'Converted sidebar link' : 'Scheduled sidebar link'
        );
    }

    public function storeScheduleSidebar(ReplaceCampaignDomainRequest $request, ScheduleSidebarCampaignTask $task)
    {
        $profile = $task->is_converted_live
            ? LiveTaskReplacementProfile::convertedScheduleSidebar()
            : LiveTaskReplacementProfile::scheduleSidebar();

        return $this->storeReplacement(
            $request,
            $profile,
            $task,
            ScheduleSidebarCampaignDomainReplacement::class,
            $task->is_converted_live ? 'converted sidebar task' : 'scheduled sidebar task'
        );
    }

    private function createForm(Request $request, LiveTaskReplacementProfile $profile, Model $task, string $taskLabel)
    {
        $task->loadMissing('campaign', $profile->taskDomainRelation.'.domain');
        $campaign = $task->campaign;
        abort_unless($campaign, 404);

        $admin = auth('admin')->user();
        $search = $request->string('search')->toString();
        $searchedDomain = null;
        $searchFeedback = null;

        if (trim($search) !== '') {
            $searchedDomain = $this->replacementService->lookupDomain($search);

            if (! $searchedDomain) {
                $searchFeedback = [
                    'type' => 'not_found',
                    'message' => 'No domain named "'.trim($search).'" exists in your inventory. Add it under Domains first.',
                ];
            } else {
                $ineligibility = $this->replacementService->ineligibilityReasonForDomain(
                    $profile,
                    $campaign,
                    $task,
                    $admin,
                    $searchedDomain,
                    manual: true
                );

                $searchFeedback = [
                    'type' => $ineligibility ? 'ineligible' : 'eligible',
                    'message' => $ineligibility
                        ?? 'This domain is eligible. Confirm the name in the replacement field below and submit.',
                    'domain' => $searchedDomain,
                ];
            }
        }

        $oldDomain = $task->{$profile->taskDomainRelation}?->domain;

        if (! $oldDomain || ($reason = $this->replacementService->ineligibleReason($profile, $task))) {
            return redirect()
                ->route($profile->showRouteName, $campaign->id)
                ->with('cus__error', $reason ?? 'The current campaign domain is no longer available.');
        }

        return view('admin.campaigns.partials.replace-live-task-domain', [
            'campaign' => $campaign,
            'task' => $task,
            'taskLabel' => $taskLabel,
            'oldDomain' => $oldDomain,
            'search' => $search,
            'searchedDomain' => $searchedDomain,
            'searchFeedback' => $searchFeedback,
            'requestUuid' => (string) Str::uuid(),
            'createRoute' => $request->route()->getName(),
            'storeRoute' => str_replace('.create', '.store', (string) $request->route()->getName()),
            'showRouteName' => $profile->showRouteName,
        ]);
    }

    /**
     * @param  class-string<Model>  $replacementModel
     */
    private function storeReplacement(
        ReplaceCampaignDomainRequest $request,
        LiveTaskReplacementProfile $profile,
        Model $task,
        string $replacementModel,
        string $taskLabel,
    ) {
        $task->loadMissing('campaign');
        $campaign = $task->campaign;
        abort_unless($campaign, 404);

        $admin = auth('admin')->user();

        try {
            $newDomainId = $this->replacementService->resolveReplacementDomainId(
                $profile,
                $campaign,
                $task,
                $admin,
                $request->input('new_domain_name'),
            );

            $replacement = $this->replacementService->replace(
                $profile,
                $task,
                $admin,
                $newDomainId,
                (int) $request->validated('expected_old_domain_id'),
                (string) $request->validated('request_uuid'),
                (string) ($request->validated('reason') ?? ''),
            );
        } catch (\Illuminate\Validation\ValidationException|\Illuminate\Auth\Access\AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            $committed = $replacementModel::query()
                ->where('request_uuid', (string) $request->validated('request_uuid'))
                ->whereIn('state', ['dispatch_pending', 'dispatching', 'dispatch_failed', 'completed'])
                ->first();

            if ($committed) {
                return redirect()
                    ->route($profile->showRouteName, $committed->{$profile->auditCampaignIdColumn} ?? $campaign->id)
                    ->with(
                        'cus__error',
                        'The domain replacement was applied, but queue dispatch could not be confirmed. '
                        .'Use Retry on the queued task; do not submit a new replacement.'
                    );
            }

            return back()
                ->withInput()
                ->with('cus__error', 'The domain could not be replaced. No task changes were applied.');
        }

        if ($replacement->state !== 'completed') {
            return redirect()
                ->route($profile->showRouteName, $replacement->{$profile->auditCampaignIdColumn} ?? $campaign->id)
                ->with(
                    'cus__error',
                    'The domain replacement was applied, but the publish job could not be queued. '
                    .'Use Retry on this queued task to safely dispatch the current generation.'
                );
        }

        return redirect()
            ->route($profile->showRouteName, $replacement->{$profile->auditCampaignIdColumn} ?? $campaign->id)
            ->with(
                'cus__success',
                $profile->supportsConvertedLive
                    ? 'Domain replaced. Republish and conversion draft jobs were queued.'
                    : 'Domain replaced and the '.$taskLabel.' was safely queued for publishing.'
            );
    }
}
