<?php

namespace Tests\Unit;

use App\Models\Admin\Campaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\WpScheduledCampaign;
use App\Services\CampaignDestructiveActionResolver;
use Tests\TestCase;

class CampaignDestructiveActionResolverTest extends TestCase
{
    public function test_it_maps_each_campaign_type_to_the_list_page_delete_action(): void
    {
        $resolver = new CampaignDestructiveActionResolver;

        $pbn = (new Campaign)->forceFill(['id' => 11]);
        $this->assertSame(route('admin.campaign.destroy', 11), $resolver->resolve($pbn)['destroy_action']);
        $this->assertSame('DELETE', $resolver->resolve($pbn)['destroy_method']);

        $sidebar = (new SidebarCampaign)->forceFill(['id' => 12]);
        $this->assertSame(
            route('admin.sidebar.campaign.destroy', 12),
            $resolver->resolve($sidebar)['destroy_action']
        );

        $hidden = (new HiddenLinksCampaign)->forceFill(['id' => 13]);
        $hiddenPayload = $resolver->resolve($hidden);
        $this->assertSame(route('admin.hidden.link.campaign.bulk.delete'), $hiddenPayload['destroy_action']);
        $this->assertSame('POST', $hiddenPayload['destroy_method']);
        $this->assertSame(
            [['name' => 'campaign_ids[]', 'value' => 13]],
            $hiddenPayload['destroy_hidden_inputs']
        );

        $schedule = (new ScheduleCampaign)->forceFill(['id' => 14]);
        $this->assertSame(
            route('admin.schedule.campaign.destroy', 14),
            $resolver->resolve($schedule)['destroy_action']
        );

        $scheduleSidebar = (new ScheduleSidebarCampaign)->forceFill(['id' => 15]);
        $this->assertSame(
            route('admin.schedule.sidebar.campaign.destroy', 15),
            $resolver->resolve($scheduleSidebar)['destroy_action']
        );

        $wpScheduled = (new WpScheduledCampaign)->forceFill(['id' => 16]);
        $this->assertSame(
            route('admin.wp.schedule.campaign.destroy', 16),
            $resolver->resolve($wpScheduled)['destroy_action']
        );
    }
}
