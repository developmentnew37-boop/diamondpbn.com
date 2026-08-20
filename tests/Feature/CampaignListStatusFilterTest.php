<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignListStatusFilterTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';

        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'campaign.pagination.default_limit' => 100,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

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

        Schema::create('admin_feature_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('permission');
            $table->timestamps();
        });

        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->boolean('viewed')->default(false);
            $table->timestamps();
        });

        Schema::create('domain_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->boolean('is_sticky_campaign')->default(false);
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 128);
            $table->timestamp('last_bulk_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('campaign_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('campaign_domain_id');
            $table->string('status')->default('queued');
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });

        Schema::create('sidebar_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 128)->nullable();
            $table->timestamp('last_bulk_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sidebar_campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('sidebar_campaign_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->text('target_url')->nullable();
            $table->string('anchor_keyword')->nullable();
            $table->timestamps();
        });

        Schema::create('sidebar_campaign_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->unsignedBigInteger('sidebar_campaign_domain_id')->nullable();
            $table->string('status')->default('queued');
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });

        Schema::create('hidden_links_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('hidden_links_campaigns_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hidden_links_campaigns_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('hidden_links_campaigns_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hidden_links_campaigns_id');
            $table->timestamps();
        });

        Schema::create('hidden_links_campaigns_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hidden_links_campaigns_id');
            $table->unsignedBigInteger('hidden_links_campaigns_domain_id')->nullable();
            $table->string('status')->default('queued');
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->boolean('is_sticky_campaign')->default(false);
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_campaigns_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('schedule_campaigns_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_campaign_id');
            $table->unsignedBigInteger('schedule_campaign_domain_id')->nullable();
            $table->string('status')->default('queued');
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_sidebar_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 128)->nullable();
            $table->date('schedule_from_date')->nullable();
            $table->date('schedule_to_date')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_sidebar_campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_sidebar_campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('schedule_sidebar_campaign_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_sidebar_campaign_id');
            $table->timestamps();
        });

        Schema::create('schedule_sidebar_campaign_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_sidebar_campaign_id');
            $table->unsignedBigInteger('schedule_sidebar_campaign_domain_id')->nullable();
            $table->string('status')->default('queued');
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->timestamps();
        });

        DB::table('domain_categories')->insert([
            'id' => 1,
            'name' => 'Default',
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_post_campaign_index_filters_by_status(): void
    {
        $admin = $this->createAdmin();
        $this->seedCampaigns($admin->id);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.campaign.index', ['status' => 'semi_failed']))
            ->assertOk()
            ->assertSee('CMP-SEMI')
            ->assertDontSee('CMP-DONE')
            ->assertDontSee('CMP-FAIL')
            ->assertDontSee('CMP-RUN')
            ->assertDontSee('CMP-QUEUE');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.campaign.index', ['status' => 'running']))
            ->assertOk()
            ->assertSee('CMP-RUN')
            ->assertDontSee('CMP-SEMI')
            ->assertDontSee('CMP-QUEUE');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.campaign.index', ['status' => 'queued']))
            ->assertOk()
            ->assertSee('CMP-QUEUE')
            ->assertDontSee('CMP-RUN')
            ->assertDontSee('CMP-STALE-DB');
    }

    public function test_stale_db_queued_with_progress_shows_under_running_not_queued(): void
    {
        $admin = $this->createAdmin();

        Campaign::query()->create([
            'campaign_no' => 'CMP-STALE-DB',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'queued', // stale DB status while work progressed
            'is_sticky_campaign' => false,
            'total_targets' => 80,
            'completed_targets' => 75,
            'failed_targets' => 0,
            'report_token' => Str::random(64),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.campaign.index', ['status' => 'queued']))
            ->assertOk()
            ->assertDontSee('CMP-STALE-DB');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.campaign.index', ['status' => 'running']))
            ->assertOk()
            ->assertSee('CMP-STALE-DB');
    }

    public function test_running_filter_tolerates_overcounted_targets(): void
    {
        $admin = $this->createAdmin();

        Campaign::query()->create([
            'campaign_no' => 'CMP-OVERCOUNT',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'running',
            'is_sticky_campaign' => false,
            'total_targets' => 10,
            'completed_targets' => 8,
            'failed_targets' => 5, // completed + failed > total (unsigned-safe path)
            'report_token' => Str::random(64),
        ]);

        Campaign::query()->create([
            'campaign_no' => 'CMP-STILL-RUN',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'running',
            'is_sticky_campaign' => false,
            'total_targets' => 10,
            'completed_targets' => 3,
            'failed_targets' => 0,
            'report_token' => Str::random(64),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.campaign.index', ['status' => 'running']))
            ->assertOk()
            ->assertSee('CMP-STILL-RUN')
            ->assertDontSee('CMP-OVERCOUNT');
    }

    public function test_post_campaign_index_rejects_invalid_status(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.campaign.index', ['status' => 'paused']))
            ->assertSessionHasErrors('status');
    }

    public function test_other_campaign_indexes_filter_by_status(): void
    {
        $admin = $this->createAdmin();

        SidebarCampaign::query()->create([
            'campaign_no' => 'SB-SEMI',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'semi_failed',
            'total_targets' => 2,
            'completed_targets' => 1,
            'failed_targets' => 1,
            'report_token' => Str::random(64),
        ]);
        SidebarCampaign::query()->create([
            'campaign_no' => 'SB-DONE',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'completed',
            'total_targets' => 2,
            'completed_targets' => 2,
            'failed_targets' => 0,
            'report_token' => Str::random(64),
        ]);

        HiddenLinksCampaign::query()->create([
            'campaign_no' => 'HL-FAIL',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'failed',
            'total_targets' => 2,
            'completed_targets' => 0,
            'failed_targets' => 2,
            'report_token' => Str::random(64),
        ]);
        HiddenLinksCampaign::query()->create([
            'campaign_no' => 'HL-DONE',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'completed',
            'total_targets' => 2,
            'completed_targets' => 2,
            'failed_targets' => 0,
            'report_token' => Str::random(64),
        ]);

        ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-QUEUE',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'queued',
            'is_sticky_campaign' => false,
            'total_targets' => 2,
            'completed_targets' => 0,
            'failed_targets' => 0,
            'report_token' => Str::random(64),
        ]);
        ScheduleCampaign::query()->create([
            'campaign_no' => 'SC-DONE',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'completed',
            'is_sticky_campaign' => false,
            'total_targets' => 2,
            'completed_targets' => 2,
            'failed_targets' => 0,
            'report_token' => Str::random(64),
        ]);

        ScheduleSidebarCampaign::query()->create([
            'campaign_no' => 'SS-RUN',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'queued',
            'total_targets' => 2,
            'completed_targets' => 1,
            'failed_targets' => 0,
            'report_token' => Str::random(64),
        ]);
        ScheduleSidebarCampaign::query()->create([
            'campaign_no' => 'SS-DONE',
            'domain_category_id' => 1,
            'admin_id' => $admin->id,
            'status' => 'completed',
            'total_targets' => 2,
            'completed_targets' => 2,
            'failed_targets' => 0,
            'report_token' => Str::random(64),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.sidebar.campaign.index', ['status' => 'semi_failed']))
            ->assertOk()
            ->assertSee('SB-SEMI')
            ->assertDontSee('SB-DONE');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.hidden.link.campaign.index', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('HL-FAIL')
            ->assertDontSee('HL-DONE');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.schedule.campaign.index', ['status' => 'queued']))
            ->assertOk()
            ->assertSee('SC-QUEUE')
            ->assertDontSee('SC-DONE');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.schedule.sidebar.campaign.index', ['status' => 'running']))
            ->assertOk()
            ->assertSee('SS-RUN')
            ->assertDontSee('SS-DONE');
    }

    private function seedCampaigns(int $adminId): void
    {
        $rows = [
            'CMP-SEMI' => ['status' => 'semi_failed', 'total' => 2, 'completed' => 1, 'failed' => 1],
            'CMP-DONE' => ['status' => 'completed', 'total' => 2, 'completed' => 2, 'failed' => 0],
            'CMP-FAIL' => ['status' => 'failed', 'total' => 2, 'completed' => 0, 'failed' => 2],
            'CMP-RUN' => ['status' => 'queued', 'total' => 80, 'completed' => 75, 'failed' => 0],
            'CMP-QUEUE' => ['status' => 'queued', 'total' => 2, 'completed' => 0, 'failed' => 0],
        ];

        foreach ($rows as $no => $row) {
            Campaign::query()->create([
                'campaign_no' => $no,
                'domain_category_id' => 1,
                'admin_id' => $adminId,
                'status' => $row['status'],
                'is_sticky_campaign' => false,
                'total_targets' => $row['total'],
                'completed_targets' => $row['completed'],
                'failed_targets' => $row['failed'],
                'report_token' => Str::random(64),
            ]);
        }
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Owner',
            'slug' => 'owner',
            'email' => 'owner@example.test',
            'password' => bcrypt('password'),
            'type' => Admin::ADMIN,
        ]);
    }
}
