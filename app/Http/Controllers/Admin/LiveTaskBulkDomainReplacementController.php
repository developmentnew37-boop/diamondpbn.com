<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesAdminCampaign;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkReplaceCampaignDomainsRequest;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use App\Services\LiveTaskDomainReplacement\LiveTaskBulkDomainReplacementService;
use App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LiveTaskBulkDomainReplacementController extends Controller
{
    use AuthorizesAdminCampaign;

    public function __construct(
        private readonly LiveTaskBulkDomainReplacementService $bulkReplacementService,
    ) {}

    public function createSidebar(Request $request, SidebarCampaign $campaign)
    {
        return $this->createForm($request, LiveTaskReplacementProfile::sidebar(), $campaign);
    }

    public function storeSidebar(BulkReplaceCampaignDomainsRequest $request, SidebarCampaign $campaign)
    {
        return $this->storeReplacement($request, LiveTaskReplacementProfile::sidebar(), $campaign);
    }

    public function createHiddenLinks(Request $request, HiddenLinksCampaign $campaign)
    {
        return $this->createForm($request, LiveTaskReplacementProfile::hiddenLinks(), $campaign);
    }

    public function storeHiddenLinks(BulkReplaceCampaignDomainsRequest $request, HiddenLinksCampaign $campaign)
    {
        return $this->storeReplacement($request, LiveTaskReplacementProfile::hiddenLinks(), $campaign);
    }

    public function createSchedulePost(Request $request, ScheduleCampaign $schedule)
    {
        return $this->createForm($request, LiveTaskReplacementProfile::schedulePost(), $schedule);
    }

    public function storeSchedulePost(BulkReplaceCampaignDomainsRequest $request, ScheduleCampaign $schedule)
    {
        return $this->storeReplacement($request, LiveTaskReplacementProfile::schedulePost(), $schedule);
    }

    public function createScheduleSidebar(Request $request, ScheduleSidebarCampaign $schedule)
    {
        return $this->createForm($request, LiveTaskReplacementProfile::scheduleSidebar(), $schedule);
    }

    public function storeScheduleSidebar(BulkReplaceCampaignDomainsRequest $request, ScheduleSidebarCampaign $schedule)
    {
        return $this->storeReplacement($request, LiveTaskReplacementProfile::scheduleSidebar(), $schedule);
    }

    private function createForm(Request $request, LiveTaskReplacementProfile $profile, Model $campaign)
    {
        $this->authorizeCampaignAccess($campaign);

        $admin = auth('admin')->user();
        $failedPrefill = implode("\n", $this->bulkReplacementService->failedDomainNamesForCampaign($profile, $campaign));

        $failedLines = $this->bulkReplacementService->parseLines(
            (string) old('failed_domains', $failedPrefill)
        );
        $replacementLines = $this->bulkReplacementService->parseLines(
            (string) old('replacement_domains', '')
        );

        $preview = [];

        if ($failedLines !== [] && $replacementLines !== []) {
            try {
                $preview = $this->bulkReplacementService->buildPreview(
                    $profile,
                    $campaign,
                    $admin,
                    $failedLines,
                    $replacementLines,
                );
            } catch (ValidationException) {
                // Validation errors are shown via $errors from redirect/back.
            }
        }

        [$itemLabel, $itemLabelPlural] = $this->itemLabels($profile);

        return view('admin.campaigns.bulk-replace-domains', [
            'campaign' => $campaign,
            'failedPrefill' => $failedPrefill,
            'preview' => $preview,
            'hasReplaceableItems' => $this->bulkReplacementService->campaignHasReplaceableTasks($profile, $campaign),
            'backUrl' => route($profile->showRouteName, $campaign->id),
            'storeRoute' => route($this->bulkStoreRouteName($profile), $campaign),
            'itemLabel' => $itemLabel,
            'itemLabelPlural' => $itemLabelPlural,
        ]);
    }

    private function storeReplacement(
        BulkReplaceCampaignDomainsRequest $request,
        LiveTaskReplacementProfile $profile,
        Model $campaign,
    ) {
        $this->authorizeCampaignAccess($campaign);

        $admin = auth('admin')->user();
        $failedLines = $this->bulkReplacementService->parseLines((string) $request->input('failed_domains', ''));
        $replacementLines = $this->bulkReplacementService->parseLines((string) $request->input('replacement_domains', ''));

        if ($replacementLines === []) {
            throw ValidationException::withMessages([
                'replacement_domains' => 'Enter at least one replacement domain.',
            ]);
        }

        $result = $this->bulkReplacementService->execute(
            $profile,
            $campaign,
            $admin,
            $failedLines,
            $replacementLines,
            (string) ($request->input('reason') ?? ''),
        );

        $message = $result->summaryMessage();
        $flashKey = $result->domainsReplaced > 0
            ? ($result->hasFailures() ? 'cus__error' : 'cus__success')
            : 'cus__error';

        return redirect()
            ->route($profile->showRouteName, $campaign->id)
            ->with($flashKey, $message);
    }

    private function bulkStoreRouteName(LiveTaskReplacementProfile $profile): string
    {
        return match ($profile->label) {
            'sidebar' => 'admin.sidebar.campaign.bulk-domain-replacement.store',
            'hidden_links' => 'admin.hidden.link.campaign.bulk-domain-replacement.store',
            'schedule_post' => 'admin.schedule.campaign.bulk-domain-replacement.store',
            'schedule_sidebar' => 'admin.schedule.sidebar.campaign.bulk-domain-replacement.store',
            default => 'admin.sidebar.campaign.bulk-domain-replacement.store',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function itemLabels(LiveTaskReplacementProfile $profile): array
    {
        return match ($profile->label) {
            'hidden_links' => ['hidden link task', 'task(s)'],
            'schedule_post' => ['scheduled post', 'post(s)'],
            'schedule_sidebar' => ['scheduled blogroll task', 'task(s)'],
            default => ['blogroll task', 'task(s)'],
        };
    }
}
