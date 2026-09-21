<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use App\Services\ConvertedCampaignReportRedirector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConvertedLiveCampaignReportTest extends TestCase
{
    private const LIVE_TOKEN = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ab';

    private const SCHEDULE_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

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
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_converted_post_report_redirects_to_schedule_report(): void
    {
        [$live, $schedule] = $this->makeConvertedPostPair();

        $this->get(route('admin.campaign.report', [
            'campaign_no' => $live->campaign_no,
            'token' => self::LIVE_TOKEN,
        ]))
            ->assertRedirect(route('admin.schedule.campaign.report', [
                'campaign_no' => $schedule->campaign_no,
                'token' => self::SCHEDULE_TOKEN,
            ]))
            ->assertSessionHas(
                ConvertedCampaignReportRedirector::FLASH_KEY,
                'This report was converted into '.$schedule->campaign_no.'.'
            );
    }

    public function test_converted_post_export_redirects_to_schedule_export(): void
    {
        [$live, $schedule] = $this->makeConvertedPostPair();

        $this->get(route('admin.campaign.report.export', [
            'campaign_no' => $live->campaign_no,
            'token' => self::LIVE_TOKEN,
        ]))->assertRedirect(route('admin.schedule.campaign.report.export', [
            'campaign_no' => $schedule->campaign_no,
            'token' => self::SCHEDULE_TOKEN,
        ]));
    }

    public function test_converted_sidebar_report_redirects_to_schedule_report(): void
    {
        [$live, $schedule] = $this->makeConvertedSidebarPair();

        $this->get(route('admin.sidebar.campaign.report', [
            'campaign_no' => $live->campaign_no,
            'token' => self::LIVE_TOKEN,
        ]))->assertRedirect(route('admin.schedule.sidebar.campaign.report', [
            'campaign_no' => $schedule->campaign_no,
            'token' => self::SCHEDULE_TOKEN,
        ]));
    }

    public function test_converted_sidebar_export_redirects_to_schedule_export(): void
    {
        [$live, $schedule] = $this->makeConvertedSidebarPair();

        $this->get(route('admin.sidebar.campaign.report.export', [
            'campaign_no' => $live->campaign_no,
            'token' => self::LIVE_TOKEN,
        ]))->assertRedirect(route('admin.schedule.sidebar.campaign.report.export', [
            'campaign_no' => $schedule->campaign_no,
            'token' => self::SCHEDULE_TOKEN,
        ]));
    }

    public function test_unconverted_post_report_still_renders_live_report(): void
    {
        $adminId = $this->adminId();
        $live = $this->insertCampaign(Campaign::class, [
            'campaign_no' => 'live-plain',
            'admin_id' => $adminId,
            'status' => 'completed',
            'report_token' => self::LIVE_TOKEN,
            'total_targets' => 0,
            'completed_targets' => 0,
            'failed_targets' => 0,
            'is_sticky_campaign' => false,
        ]);

        $this->get(route('admin.campaign.report', [
            'campaign_no' => $live->campaign_no,
            'token' => self::LIVE_TOKEN,
        ]))
            ->assertOk()
            ->assertSee('live-plain')
            ->assertSee('Campaign Report')
            ->assertDontSee('This campaign report is inactive');
    }

    public function test_missing_converted_target_shows_inactive_page(): void
    {
        $adminId = $this->adminId();
        $this->insertCampaign(Campaign::class, [
            'campaign_no' => 'live-orphan',
            'admin_id' => $adminId,
            'status' => 'completed',
            'report_token' => self::LIVE_TOKEN,
            'converted_to_schedule_campaign_id' => 999,
            'is_sticky_campaign' => false,
        ]);

        $this->get(route('admin.campaign.report', [
            'campaign_no' => 'live-orphan',
            'token' => self::LIVE_TOKEN,
        ]))
            ->assertStatus(410)
            ->assertSee('This campaign report is inactive')
            ->assertSee('live-orphan')
            ->assertDontSee('>Live<', false);
    }

    /**
     * @return array{0: Campaign, 1: ScheduleCampaign}
     */
    private function makeConvertedPostPair(): array
    {
        $adminId = $this->adminId();
        $live = $this->insertCampaign(Campaign::class, [
            'campaign_no' => 'live-source',
            'admin_id' => $adminId,
            'status' => 'completed',
            'report_token' => self::LIVE_TOKEN,
            'is_sticky_campaign' => false,
        ]);
        $schedule = $this->insertCampaign(ScheduleCampaign::class, [
            'campaign_no' => 'sched-converted',
            'admin_id' => $adminId,
            'status' => 'running',
            'report_token' => self::SCHEDULE_TOKEN,
            'converted_from_campaign_id' => $live->id,
            'is_sticky_campaign' => false,
        ]);
        $live->forceFill(['converted_to_schedule_campaign_id' => $schedule->id])->save();

        return [$live->fresh(), $schedule];
    }

    /**
     * @return array{0: SidebarCampaign, 1: ScheduleSidebarCampaign}
     */
    private function makeConvertedSidebarPair(): array
    {
        $adminId = $this->adminId();
        $live = $this->insertCampaign(SidebarCampaign::class, [
            'campaign_no' => 'sidebar-source',
            'admin_id' => $adminId,
            'status' => 'completed',
            'report_token' => self::LIVE_TOKEN,
        ]);
        $schedule = $this->insertCampaign(ScheduleSidebarCampaign::class, [
            'campaign_no' => 'sidebar-converted',
            'admin_id' => $adminId,
            'status' => 'running',
            'report_token' => self::SCHEDULE_TOKEN,
            'converted_from_sidebar_campaign_id' => $live->id,
        ]);
        $live->forceFill(['converted_to_schedule_sidebar_campaign_id' => $schedule->id])->save();

        return [$live->fresh(), $schedule];
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<string, mixed>  $attributes
     */
    private function insertCampaign(string $model, array $attributes): \Illuminate\Database\Eloquent\Model
    {
        $campaign = new $model;
        $campaign->forceFill($attributes);
        $campaign->save();

        return $campaign;
    }

    private function adminId(): int
    {
        return (int) DB::table('admins')->insertGetId([
            'name' => 'Owner',
            'slug' => 'owner',
            'email' => 'owner-converted@example.test',
            'password' => bcrypt('password'),
            'type' => Admin::ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
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

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->string('status')->default('queued');
            $table->boolean('is_sticky_campaign')->default(false);
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 128);
            $table->unsignedBigInteger('converted_to_schedule_campaign_id')->nullable();
            $table->timestamp('conversion_locked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->boolean('is_sticky_campaign')->default(false);
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 128);
            $table->unsignedBigInteger('converted_from_campaign_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sidebar_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 128);
            $table->unsignedBigInteger('converted_to_schedule_sidebar_campaign_id')->nullable();
            $table->timestamp('conversion_locked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_sidebar_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('admin_id');
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 128);
            $table->unsignedBigInteger('converted_from_sidebar_campaign_id')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('campaign_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('campaign_domain_id')->nullable();
            $table->unsignedBigInteger('campaign_article_id')->nullable();
            $table->string('status')->default('queued');
            $table->timestamps();
        });

        Schema::create('sidebar_campaign_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->string('status')->default('queued');
            $table->timestamps();
        });
    }
}
