<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\CampaignArticle;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\HiddenLinksCampaignLinks;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleCampaignArticle;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\ScheduleSidebarCampaignLink;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignLink;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CampaignKeywordUrlLookupTest extends TestCase
{
    private const TARGET = 'https://www.example.com/promo/landing';

    private const TOKEN = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ab';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
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

        foreach (['campaigns', 'sidebar_campaigns', 'hidden_links_campaigns', 'schedule_campaigns', 'schedule_sidebar_campaigns'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->string('campaign_no');
                $table->unsignedBigInteger('admin_id');
                $table->string('status')->default('completed');
                $table->unsignedInteger('total_targets')->default(1);
                $table->unsignedInteger('completed_targets')->default(0);
                $table->unsignedInteger('failed_targets')->default(0);
                $table->string('report_token', 128);
                if (in_array($tableName, ['campaigns', 'schedule_campaigns'], true)) {
                    $table->boolean('is_sticky_campaign')->default(false);
                }
                $table->timestamps();
            });
        }

        Schema::create('campaign_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('article_id')->nullable();
            $table->text('keyword')->nullable();
            $table->text('url')->nullable();
            $table->timestamps();
        });

        Schema::create('sidebar_campaign_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->text('target_url');
            $table->string('anchor_keyword')->nullable();
            $table->timestamps();
        });

        Schema::create('hidden_links_campaigns_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hidden_links_campaigns_id');
            $table->text('target_url');
            $table->string('anchor_keyword')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_campaigns_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_campaign_id');
            $table->unsignedBigInteger('article_id')->nullable();
            $table->text('keyword')->nullable();
            $table->text('url')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_sidebar_campaign_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_sidebar_campaign_id');
            $table->text('target_url');
            $table->string('anchor_keyword')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach ([
            'schedule_sidebar_campaign_links',
            'schedule_campaigns_articles',
            'hidden_links_campaigns_links',
            'sidebar_campaign_links',
            'campaign_articles',
            'schedule_sidebar_campaigns',
            'schedule_campaigns',
            'hidden_links_campaigns',
            'sidebar_campaigns',
            'campaigns',
            'pending_domains',
            'admins',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        parent::tearDown();
    }

    public function test_keyword_url_search_finds_all_five_campaign_types(): void
    {
        $admin = $this->createAdmin(Admin::ADMIN, 'owner@example.test');
        $this->actingAs($admin, 'admin');

        $pbn = $this->createCampaign(Campaign::class, 'pbn-kw', $admin->id);
        CampaignArticle::query()->create([
            'campaign_id' => $pbn->id,
            'keyword' => 'Promo',
            'url' => self::TARGET,
        ]);

        $sidebar = $this->createCampaign(SidebarCampaign::class, 'sidebar-kw', $admin->id);
        SidebarCampaignLink::query()->create([
            'sidebar_campaign_id' => $sidebar->id,
            'anchor_keyword' => 'Sidebar KW',
            'target_url' => 'http://example.com/promo/landing/',
        ]);

        $hidden = $this->createCampaign(HiddenLinksCampaign::class, 'hidden-kw', $admin->id);
        HiddenLinksCampaignLinks::query()->create([
            'hidden_links_campaigns_id' => $hidden->id,
            'anchor_keyword' => 'Hidden KW',
            'target_url' => json_encode([self::TARGET, 'https://other.example']),
        ]);

        $schedule = $this->createCampaign(ScheduleCampaign::class, 'schedule-kw', $admin->id);
        ScheduleCampaignArticle::query()->create([
            'schedule_campaign_id' => $schedule->id,
            'keyword' => 'Sched',
            'url' => self::TARGET,
        ]);

        $scheduleSidebar = $this->createCampaign(ScheduleSidebarCampaign::class, 'sched-sidebar-kw', $admin->id);
        ScheduleSidebarCampaignLink::query()->create([
            'schedule_sidebar_campaign_id' => $scheduleSidebar->id,
            'anchor_keyword' => 'Drip Sidebar',
            'target_url' => self::TARGET,
        ]);

        $response = $this->post(route('admin.reports.find-campaign.by-keyword-url'), [
            'keyword_url' => 'example.com/promo/landing',
        ]);

        $response->assertOk()
            ->assertSee('5 campaigns found')
            ->assertSee('PBN Post')
            ->assertSee('Sidebar Campaign')
            ->assertSee('Hidden Links Campaign')
            ->assertSee('Scheduled PBN Post')
            ->assertSee('Scheduled Sidebar Campaign')
            ->assertSee('pbn-kw')
            ->assertSee('sidebar-kw')
            ->assertSee('hidden-kw')
            ->assertSee('schedule-kw')
            ->assertSee('sched-sidebar-kw')
            ->assertSee('Promo')
            ->assertSee('Hidden KW');
    }

    public function test_admin_cannot_see_other_users_campaigns_by_keyword_url(): void
    {
        $owner = $this->createAdmin(Admin::ADMIN, 'owner@example.test');
        $other = $this->createAdmin(Admin::ADMIN, 'other@example.test');

        $campaign = $this->createCampaign(Campaign::class, 'private-kw', $owner->id);
        CampaignArticle::query()->create([
            'campaign_id' => $campaign->id,
            'keyword' => 'Secret',
            'url' => self::TARGET,
        ]);

        $this->actingAs($other, 'admin')
            ->post(route('admin.reports.find-campaign.by-keyword-url'), [
                'keyword_url' => self::TARGET,
            ])
            ->assertOk()
            ->assertSee('No campaigns found using that URL');

        $this->actingAs($owner, 'admin')
            ->post(route('admin.reports.find-campaign.by-keyword-url'), [
                'keyword_url' => self::TARGET,
            ])
            ->assertOk()
            ->assertSee('private-kw')
            ->assertDontSee('No campaigns found using that URL');
    }

    public function test_super_admin_sees_all_campaigns_by_keyword_url(): void
    {
        $owner = $this->createAdmin(Admin::ADMIN, 'owner@example.test');
        $super = $this->createAdmin(Admin::SUPER_ADMIN, 'super@example.test');

        $campaign = $this->createCampaign(Campaign::class, 'super-visible', $owner->id);
        CampaignArticle::query()->create([
            'campaign_id' => $campaign->id,
            'url' => self::TARGET,
        ]);

        $this->actingAs($super, 'admin')
            ->post(route('admin.reports.find-campaign.by-keyword-url'), [
                'keyword_url' => self::TARGET,
            ])
            ->assertOk()
            ->assertSee('super-visible');
    }

    public function test_member_is_denied_keyword_url_lookup(): void
    {
        $member = $this->createAdmin(Admin::MEMBER, 'member@example.test');
        $this->actingAs($member, 'admin');

        $this->get(route('admin.reports.find-campaign'))
            ->assertRedirect(route('admin.dashboard'));

        $this->post(route('admin.reports.find-campaign.by-keyword-url'), [
            'keyword_url' => self::TARGET,
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
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function createCampaign(string $model, string $campaignNo, int $adminId): \Illuminate\Database\Eloquent\Model
    {
        $campaign = new $model;
        $attributes = [
            'campaign_no' => $campaignNo,
            'admin_id' => $adminId,
            'status' => 'completed',
            'total_targets' => 1,
            'completed_targets' => 0,
            'failed_targets' => 0,
            'report_token' => self::TOKEN,
        ];

        $campaign->forceFill($attributes);
        $campaign->save();

        return $campaign;
    }
}
