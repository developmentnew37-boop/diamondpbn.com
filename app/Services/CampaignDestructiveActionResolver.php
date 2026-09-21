<?php

namespace App\Services;

use App\Models\Admin\Campaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\WpScheduledCampaign;
use Illuminate\Database\Eloquent\Model;

class CampaignDestructiveActionResolver
{
    /**
     * @return array{
     *     destroy_action: string|null,
     *     destroy_method: string,
     *     destroy_hidden_inputs: list<array{name: string, value: int|string}>,
     *     destroy_confirm: string
     * }
     */
    public function resolve(Model $campaign): array
    {
        $id = $campaign->getKey();

        return match ($campaign::class) {
            Campaign::class => [
                'destroy_action' => route('admin.campaign.destroy', $id),
                'destroy_method' => 'DELETE',
                'destroy_hidden_inputs' => [],
                'destroy_confirm' => 'Delete this campaign? All campaign posts will be removed from the database and from remote sites. This cannot be undone.',
            ],
            SidebarCampaign::class => [
                'destroy_action' => route('admin.sidebar.campaign.destroy', $id),
                'destroy_method' => 'DELETE',
                'destroy_hidden_inputs' => [],
                'destroy_confirm' => 'Delete this sidebar campaign? All tasks will be removed from the database and from remote blogroll. This cannot be undone.',
            ],
            HiddenLinksCampaign::class => [
                'destroy_action' => route('admin.hidden.link.campaign.bulk.delete'),
                'destroy_method' => 'POST',
                'destroy_hidden_inputs' => [
                    ['name' => 'campaign_ids[]', 'value' => $id],
                ],
                'destroy_confirm' => 'Delete this campaign? Links will be removed from remote sites, then the campaign and its data will be deleted. This cannot be undone.',
            ],
            ScheduleCampaign::class => [
                'destroy_action' => route('admin.schedule.campaign.destroy', $id),
                'destroy_method' => 'DELETE',
                'destroy_hidden_inputs' => [],
                'destroy_confirm' => 'Delete this campaign? All posts will be removed from the database and from the remote site. This cannot be undone.',
            ],
            ScheduleSidebarCampaign::class => [
                'destroy_action' => route('admin.schedule.sidebar.campaign.destroy', $id),
                'destroy_method' => 'DELETE',
                'destroy_hidden_inputs' => [],
                'destroy_confirm' => 'Delete this campaign? All blogroll links will be removed from remote sites and from the database. This cannot be undone.',
            ],
            WpScheduledCampaign::class => [
                'destroy_action' => route('admin.wp.schedule.campaign.destroy', $id),
                'destroy_method' => 'DELETE',
                'destroy_hidden_inputs' => [],
                'destroy_confirm' => 'Delete this entire campaign? All posts will be removed from WordPress and the database. This cannot be undone.',
            ],
            default => [
                'destroy_action' => null,
                'destroy_method' => 'DELETE',
                'destroy_hidden_inputs' => [],
                'destroy_confirm' => 'Delete this campaign? This cannot be undone.',
            ],
        };
    }
}
