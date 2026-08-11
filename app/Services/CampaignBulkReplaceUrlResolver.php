<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use App\Services\LiveTaskDomainReplacement\LiveTaskBulkDomainReplacementService;
use App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile;
use Illuminate\Database\Eloquent\Model;

class CampaignBulkReplaceUrlResolver
{
    public function __construct(
        private readonly CampaignBulkDomainReplacementService $postBulkReplacementService,
        private readonly LiveTaskBulkDomainReplacementService $liveTaskBulkReplacementService,
    ) {}

    public function resolveUrl(Model $campaign, Admin $admin): ?string
    {
        if (! $admin->canCreateCampaigns()) {
            return null;
        }

        $routeName = $this->routeNameFor($campaign);

        if ($routeName === null || ! $this->hasReplaceableItems($campaign)) {
            return null;
        }

        return route($routeName, $campaign);
    }

    private function routeNameFor(Model $campaign): ?string
    {
        return match ($campaign::class) {
            Campaign::class => 'admin.campaign.bulk-domain-replacement.create',
            SidebarCampaign::class => 'admin.sidebar.campaign.bulk-domain-replacement.create',
            HiddenLinksCampaign::class => 'admin.hidden.link.campaign.bulk-domain-replacement.create',
            ScheduleCampaign::class => 'admin.schedule.campaign.bulk-domain-replacement.create',
            ScheduleSidebarCampaign::class => 'admin.schedule.sidebar.campaign.bulk-domain-replacement.create',
            default => null,
        };
    }

    private function hasReplaceableItems(Model $campaign): bool
    {
        if ($campaign instanceof Campaign) {
            return $this->postBulkReplacementService->campaignHasReplaceablePosts($campaign);
        }

        $profile = match ($campaign::class) {
            SidebarCampaign::class => LiveTaskReplacementProfile::sidebar(),
            HiddenLinksCampaign::class => LiveTaskReplacementProfile::hiddenLinks(),
            ScheduleCampaign::class => LiveTaskReplacementProfile::schedulePost(),
            ScheduleSidebarCampaign::class => LiveTaskReplacementProfile::scheduleSidebar(),
            default => null,
        };

        if ($profile === null) {
            return false;
        }

        return $this->liveTaskBulkReplacementService->campaignHasReplaceableTasks($profile, $campaign);
    }
}
