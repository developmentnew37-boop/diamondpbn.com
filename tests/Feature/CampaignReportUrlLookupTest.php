<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\WpScheduledCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CampaignReportUrlLookupTest extends TestCase
{
    private const TOKEN = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ab';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('type');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->boolean('viewed')->default(false);
            $table->timestamps();
        });

        Schema::create('admin_feature_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id');
            $table->string('permission');
            $table->timestamps();
            $table->unique(['admin_id', 'permission']);
        });

        foreach ([
            'campaigns',
            'sidebar_campaigns',
            'hidden_links_campaigns',
            'schedule_campaigns',
            'schedule_sidebar_campaigns',
            'wp_scheduled_campaigns',
        ] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->string('campaign_no');
                $table->unsignedBigInteger('admin_id');
                $table->string('status')->default('completed');
                $table->unsignedInteger('total_targets')->default(0);
                $table->unsignedInteger('completed_targets')->default(0);
                $table->unsignedInteger('failed_targets')->default(0);
                $table->string('report_token', 128);
                if (in_array($tableName, ['campaigns', 'schedule_campaigns'], true)) {
                    $table->boolean('is_sticky_campaign')->default(false);
                }
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        foreach ([
            'wp_scheduled_campaigns',
            'schedule_sidebar_campaigns',
            'schedule_campaigns',
            'hidden_links_campaigns',
            'sidebar_campaigns',
            'campaigns',
            'admin_feature_permissions',
            'pending_domains',
            'admins',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        parent::tearDown();
    }

    public function test_super_admin_can_resolve_every_report_route_family_and_export_variant(): void
    {
        $superAdmin = $this->createAdmin(Admin::SUPER_ADMIN, 'super@example.test');
        $this->actingAs($superAdmin, 'admin');

        $cases = [
            [Campaign::class, 'campaign/report', 'PBN Post', false],
            [Campaign::class, 'campaign/report', 'Sticky PBN Post', true],
            [SidebarCampaign::class, 'sidebar/campaign/report', 'Sidebar Campaign', false],
            [HiddenLinksCampaign::class, 'hidden/link/campaign/report', 'Hidden Links Campaign', false],
            [ScheduleCampaign::class, 'schedule/campaign/report', 'Scheduled PBN Post', false],
            [ScheduleCampaign::class, 'schedule/campaign/report', 'Scheduled Sticky PBN Post', true],
            [ScheduleSidebarCampaign::class, 'schedule/sidebar/campaign/report', 'Scheduled Sidebar Campaign', false],
            [WpScheduledCampaign::class, 'campaign/post/wp-schedule/report', 'WordPress Scheduled Campaign', false],
        ];

        foreach ($cases as $index => [$model, $path, $expectedType, $sticky]) {
            $campaignNo = "lookup-{$index}";
            $this->createCampaign($model, $campaignNo, $superAdmin->id, self::TOKEN, $sticky);

            $input = $index % 2 === 0
                ? "/{$path}/{$campaignNo}/".self::TOKEN.'/export'
                : url("/{$path}/{$campaignNo}/".self::TOKEN);

            $response = $this->post(route('admin.reports.find-campaign.lookup'), [
                'report_url' => $input,
            ]);

            $response->assertOk()
                ->assertSee($expectedType)
                ->assertSee($campaignNo)
                ->assertSee('Super Admin')
                ->assertSee('12')
                ->assertSee('9')
                ->assertSee('2')
                ->assertSee('Campaign found')
                ->assertSee('Edit campaign', false)
                ->assertSee(url("/{$path}/{$campaignNo}/".self::TOKEN), false)
                ->assertDontSee("{$campaignNo}/".self::TOKEN.'/export', false);
        }
    }

    public function test_lookup_rejects_external_malformed_and_wrong_token_urls_generically(): void
    {
        $admin = $this->createAdmin(Admin::ADMIN, 'admin@example.test');
        $this->createCampaign(Campaign::class, 'secure-campaign', $admin->id);
        $this->actingAs($admin, 'admin');

        $invalidUrls = [
            'https://evil.example/campaign/report/secure-campaign/'.self::TOKEN,
            'ftp://localhost/campaign/report/secure-campaign/'.self::TOKEN,
            'http://user@localhost/campaign/report/secure-campaign/'.self::TOKEN,
            '/campaign/report/secure-campaign/'.self::TOKEN.'?leak=1',
            '/campaign/report/secure-campaign/'.self::TOKEN.'#fragment',
            '/campaign/report/secure-campaign/wrongtoken',
            '/unsupported/report/secure-campaign/'.self::TOKEN,
            '/campaign/report/secure%2Dcampaign/'.self::TOKEN,
        ];

        foreach ($invalidUrls as $url) {
            $this->post(route('admin.reports.find-campaign.lookup'), ['report_url' => $url])
                ->assertOk()
                ->assertSee('Campaign not found or you do not have permission to view it.')
                ->assertDontSee($url);
        }
    }

    public function test_route_family_prevents_cross_type_campaign_number_matches(): void
    {
        $admin = $this->createAdmin(Admin::ADMIN, 'admin@example.test');
        $this->createCampaign(Campaign::class, 'duplicate-number', $admin->id, str_repeat('A', 64));
        $this->createCampaign(SidebarCampaign::class, 'duplicate-number', $admin->id, self::TOKEN);
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.reports.find-campaign.lookup'), [
            'report_url' => '/sidebar/campaign/report/duplicate-number/'.self::TOKEN,
        ])->assertOk()
            ->assertSee('>Sidebar Campaign</td>', false)
            ->assertDontSee('>PBN Post</td>', false);
    }

    public function test_normal_admin_only_resolves_owned_campaigns_while_super_admin_resolves_all(): void
    {
        $owner = $this->createAdmin(Admin::ADMIN, 'owner@example.test');
        $otherAdmin = $this->createAdmin(Admin::ADMIN, 'other@example.test');
        $superAdmin = $this->createAdmin(Admin::SUPER_ADMIN, 'super@example.test');
        $this->createCampaign(Campaign::class, 'owned-campaign', $owner->id);
        $url = '/campaign/report/owned-campaign/'.self::TOKEN;

        $this->actingAs($otherAdmin, 'admin')
            ->post(route('admin.reports.find-campaign.lookup'), ['report_url' => $url])
            ->assertOk()
            ->assertSee('Campaign not found or you do not have permission to view it.');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.reports.find-campaign.lookup'), ['report_url' => $url])
            ->assertOk()
            ->assertSee('owned-campaign')
            ->assertSee('Campaign Owner');
    }

    public function test_member_is_denied_by_campaign_permission_middleware(): void
    {
        $member = $this->createAdmin(Admin::MEMBER, 'member@example.test');
        $this->actingAs($member, 'admin');

        $this->get(route('admin.reports.find-campaign'))
            ->assertRedirect(route('admin.dashboard'));

        $this->post(route('admin.reports.find-campaign.lookup'), [
            'report_url' => '/campaign/report/anything/'.self::TOKEN,
        ])->assertRedirect(route('admin.dashboard'));
    }

    private function createAdmin(int $type, string $email): Admin
    {
        return Admin::query()->create([
            'name' => $type === Admin::SUPER_ADMIN ? 'Super Admin' : 'Campaign Owner',
            'email' => $email,
            'password' => 'password',
            'type' => $type,
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function createCampaign(
        string $model,
        string $campaignNo,
        int $adminId,
        string $token = self::TOKEN,
        bool $sticky = false
    ): Model {
        $campaign = new $model;
        $attributes = [
            'campaign_no' => $campaignNo,
            'admin_id' => $adminId,
            'status' => 'completed',
            'total_targets' => 12,
            'completed_targets' => 9,
            'failed_targets' => 2,
            'report_token' => $token,
        ];
        if ($campaign instanceof Campaign || $campaign instanceof ScheduleCampaign) {
            $attributes['is_sticky_campaign'] = $sticky;
        }
        $campaign->forceFill($attributes);
        $campaign->save();

        return $campaign;
    }
}
