<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CampaignDomainReplacementRouteUiTest extends TestCase
{
    public function test_replacement_routes_are_authenticated_and_campaign_permission_protected(): void
    {
        foreach ([
            'admin.campaign.domain-replacement.create' => 'GET',
            'admin.campaign.domain-replacement.store' => 'POST',
        ] as $name => $method) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertContains($method, $route->methods());
            $this->assertContains('admin.auth', $route->gatherMiddleware());
            $this->assertContains(
                \App\Http\Middleware\Admin\CanCreateCampaigns::class,
                $route->gatherMiddleware()
            );
        }
    }

    public function test_campaign_ui_only_offers_replacement_through_safety_check(): void
    {
        $campaignView = file_get_contents(
            resource_path('views/admin/campaigns/pbn-post/view-campaign.blade.php')
        );
        $replacementView = file_get_contents(
            resource_path('views/admin/campaigns/pbn-post/replace-domain.blade.php')
        );
        $controller = file_get_contents(
            app_path('Http/Controllers/Admin/CampaignDomainReplacementController.php')
        );

        $this->assertStringContainsString('CampaignDomainReplacementService::ineligibleReason', $campaignView);
        $this->assertStringContainsString("in_array(\$post->status, ['queued', 'failed']", $campaignView);
        $this->assertStringContainsString('domain-replacement.create', $campaignView);
        $this->assertStringContainsString('swap_horiz', $campaignView);
        $this->assertStringContainsString('expected_old_domain_id', $replacementView);
        $this->assertStringContainsString('new_domain_name', $replacementView);
        $this->assertStringContainsString('keyword_url', file_get_contents(
            resource_path('views/admin/reports/find-campaign.blade.php')
        ));
        $this->assertStringContainsString('request_uuid', $replacementView);
        $this->assertStringContainsString('Reason for replacement', $replacementView);
        $this->assertStringContainsString('Look up', $replacementView);
        $this->assertStringContainsString('!p-5', $replacementView);
        $this->assertStringContainsString(
            'The domain replacement was applied, but the publish job could not be queued.',
            $controller
        );
        $this->assertStringContainsString(
            "->whereIn('state', ['dispatch_pending', 'dispatching', 'dispatch_failed', 'completed'])",
            $controller
        );
    }

    public function test_manual_retry_is_csrf_protected_and_uses_the_same_delivery_safety_gate(): void
    {
        $route = Route::getRoutes()->getByName('admin.campaign.retry');
        $campaignView = file_get_contents(
            resource_path('views/admin/campaigns/pbn-post/view-campaign.blade.php')
        );

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
        $this->assertStringContainsString('$replacementReason === null', $campaignView);
        $this->assertStringContainsString('@csrf', $campaignView);
    }
}
