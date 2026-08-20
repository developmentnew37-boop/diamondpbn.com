<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Domain;
use App\Models\Admin\SidebarCampaign;
use App\Models\Admin\SidebarCampaignDomain;
use App\Models\Admin\SidebarCampaignLink;
use App\Models\Admin\SidebarCampaignTask;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SidebarCampaignDomainOrderTest extends TestCase
{
    private const PASTE_ORDER = [
        'sherill.us',
        'trustoil.us',
        'sporeach.us',
        'knotlore.us',
        'boyparis.us',
        'gascomp.us',
        'tomtommy.us',
        'minisaw.us',
        'lotthai.us',
        'mybooth.us',
    ];

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

    private function createSchema(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('email');
            $table->string('password');
            $table->tinyInteger('type');
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

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('sidebar_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedInteger('sidebar_count')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->string('report_token', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_bulk_updated_at')->nullable();
            $table->unsignedBigInteger('converted_to_schedule_sidebar_campaign_id')->nullable();
            $table->timestamp('conversion_locked_at')->nullable();
            $table->unsignedBigInteger('local_client_id')->nullable();
            $table->decimal('billing_total', 10, 2)->nullable();
            $table->string('billing_currency', 8)->nullable();
            $table->json('billing_snapshot')->nullable();
            $table->string('billing_payment_status')->nullable();
            $table->timestamp('billing_paid_at')->nullable();
            $table->unsignedBigInteger('billing_paid_by_admin_id')->nullable();
            $table->text('billing_payment_note')->nullable();
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
            $table->string('target_url')->nullable();
            $table->string('anchor_keyword')->nullable();
            $table->boolean('nofollow')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sidebar_campaign_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->unsignedBigInteger('sidebar_campaign_domain_id');
            $table->unsignedBigInteger('sidebar_campaign_link_id')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('remote_id')->nullable();
            $table->string('remote_url')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('content_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_show_and_report_list_domains_in_paste_order(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'email' => 'super@example.test',
            'password' => 'password',
            'type' => Admin::SUPER_ADMIN,
        ]);
        $this->actingAs($admin, 'admin');

        $campaign = SidebarCampaign::query()->create([
            'campaign_no' => 'testing-ascending-descending',
            'admin_id' => $admin->id,
            'status' => 'completed',
            'total_targets' => count(self::PASTE_ORDER),
            'completed_targets' => count(self::PASTE_ORDER),
            'failed_targets' => 0,
            'report_token' => 'token-'.str_repeat('a', 48),
        ]);

        foreach (self::PASTE_ORDER as $index => $name) {
            $domain = Domain::query()->create([
                'name' => $name,
                'admin_id' => $admin->id,
                'status' => 1,
            ]);

            $domainRow = SidebarCampaignDomain::query()->create([
                'sidebar_campaign_id' => $campaign->id,
                'domain_id' => $domain->id,
            ]);

            $link = SidebarCampaignLink::query()->create([
                'sidebar_campaign_id' => $campaign->id,
                'target_url' => 'https://example.com/'.$name,
                'anchor_keyword' => 'kw-'.$index,
                'nofollow' => false,
                'sort_order' => $index + 1,
            ]);

            SidebarCampaignTask::query()->create([
                'sidebar_campaign_id' => $campaign->id,
                'sidebar_campaign_domain_id' => $domainRow->id,
                'sidebar_campaign_link_id' => $link->id,
                'status' => 'success',
                'attempt_count' => 0,
                'remote_id' => 'blog_'.$index,
            ]);
        }

        $show = $this->get(route('admin.sidebar.campaign.show', $campaign->id));
        $show->assertOk();
        $showDomains = $show->viewData('campaignTasks')->pluck('domainRow.domain.name')->all();
        $this->assertSame(self::PASTE_ORDER, $showDomains);

        $report = $this->get(route('admin.sidebar.campaign.report', [
            'campaign_no' => $campaign->campaign_no,
            'token' => $campaign->report_token,
        ]));
        $report->assertOk();
        $reportDomains = $report->viewData('tasks')->pluck('domainRow.domain.name')->all();
        $this->assertSame(self::PASTE_ORDER, $reportDomains);
        $this->assertSame('sherill.us', $reportDomains[0]);
        $this->assertSame('mybooth.us', $reportDomains[array_key_last($reportDomains)]);
    }
}
