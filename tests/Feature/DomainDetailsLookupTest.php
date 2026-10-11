<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainDetailsLookupTest extends TestCase
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
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

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

        Schema::create('local_client_rate_lists', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('local_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rate_list_id')->nullable();
        });

        Schema::create('domain_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('da')->default(0);
            $table->integer('dr')->default(0);
            $table->integer('tf')->default(0);
            $table->integer('ss')->default(0);
            $table->string('ip')->nullable();
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->integer('status')->default(0);
            $table->string('agent_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_status_message')->nullable();
            $table->text('api_key')->nullable();
            $table->string('api_key_lookup_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->integer('qty')->default(0);
            $table->json('domains');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_no');
            $table->boolean('is_sticky_campaign')->default(false);
            $table->timestamps();
        });

        Schema::create('campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        foreach ([
            'sidebar_campaigns',
            'hidden_links_campaigns',
            'schedule_campaigns',
            'schedule_sidebar_campaigns',
            'wp_scheduled_campaigns',
        ] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('campaign_no');
                $table->timestamps();
            });
        }

        Schema::create('sidebar_campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sidebar_campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('hidden_links_campaigns_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hidden_links_campaigns_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('schedule_campaigns_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('schedule_sidebar_campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_sidebar_campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });

        Schema::create('wp_scheduled_campaign_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wp_scheduled_campaign_id');
            $table->unsignedBigInteger('domain_id');
            $table->timestamps();
        });
    }

    public function test_known_domain_shows_category_adder_and_campaign_without_secrets(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $category = DomainCategory::query()->create(['name' => 'News Sites', 'slug' => 'news-sites']);
        $domain = Domain::query()->create([
            'name' => 'example.com',
            'domain_category_id' => $category->id,
            'admin_id' => $admin->id,
            'status' => 1,
            'da' => 81,
            'dr' => 82,
            'tf' => 83,
            'ss' => 84,
            'ip' => '203.0.113.44',
            'api_key' => 'secret-domain-api-key-9f3a',
            'agent_version' => '7.8.0',
            'last_status_message' => 'Agent online',
        ]);

        DB::table('campaigns')->insert([
            'id' => 15,
            'campaign_no' => 'CMP-100',
            'is_sticky_campaign' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('campaign_domains')->insert([
            'campaign_id' => 15,
            'domain_id' => $domain->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $set = new DomainSet([
            'name' => 'News pack',
            'qty' => 1,
            'domain_category_id' => $category->id,
            'admin_id' => $admin->id,
        ]);
        $set->domains = json_encode([$domain->id]);
        $set->save();

        $response = $this->get(route('admin.domain.details', ['domain' => 'https://Example.com/page']));

        $response->assertOk()
            ->assertSee('News Sites')
            ->assertSee($admin->name)
            ->assertSee('Connected')
            ->assertSee('CMP-100')
            ->assertSee('PBN post')
            ->assertSee('News pack')
            ->assertSee('Agent online')
            ->assertSee('7.8.0')
            ->assertDontSee('secret-domain-api-key-9f3a')
            ->assertDontSee('203.0.113.44')
            ->assertDontSee('Spam score')
            ->assertDontSee('Domain authority')
            ->assertDontSee('Trust Flow');
    }

    public function test_campaigns_stay_on_separate_pages(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $category = DomainCategory::query()->create(['name' => 'Paged', 'slug' => 'paged']);
        $domain = Domain::query()->create([
            'name' => 'paged.example',
            'domain_category_id' => $category->id,
            'admin_id' => $admin->id,
            'status' => 1,
            'api_key' => 'unused-key',
        ]);

        for ($number = 1; $number <= 21; $number++) {
            DB::table('campaigns')->insert([
                'id' => $number,
                'campaign_no' => sprintf('page-campaign-%02d', $number),
                'is_sticky_campaign' => 0,
                'created_at' => Carbon::parse('2026-01-01 00:00:00')->addMinutes($number),
                'updated_at' => now(),
            ]);
            DB::table('campaign_domains')->insert([
                'campaign_id' => $number,
                'domain_id' => $domain->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $page1 = $this->get(route('admin.domain.details', ['domain' => 'paged.example']));

        $page1->assertOk()
            ->assertSee('Used in 21 campaigns')
            ->assertSee('PBN post 21')
            ->assertSee('page-campaign-21')
            ->assertSee('page-campaign-02')
            ->assertDontSee('page-campaign-01');

        $page2 = $this->get(route('admin.domain.details', [
            'domain' => 'paged.example',
            'campaigns_page' => 2,
        ]));

        $page2->assertOk()
            ->assertSee('page-campaign-01')
            ->assertDontSee('page-campaign-21')
            ->assertDontSee('page-campaign-02');
    }

    public function test_www_alias_finds_the_stored_domain(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $category = DomainCategory::query()->create(['name' => 'Blogs', 'slug' => 'blogs']);
        Domain::query()->create([
            'name' => 'example.com',
            'domain_category_id' => $category->id,
            'admin_id' => $admin->id,
            'status' => 1,
            'api_key' => 'unused-key',
        ]);

        $this->get(route('admin.domain.details', ['domain' => 'www.example.com']))
            ->assertOk()
            ->assertSee('example.com')
            ->assertSee('Blogs')
            ->assertDontSee('This domain is not in the inventory.');
    }

    public function test_blank_search_does_not_say_the_domain_is_missing(): void
    {
        $this->actingAs($this->createAdmin(), 'admin');

        $this->get(route('admin.domain.details'))
            ->assertOk()
            ->assertSee('Domain Details')
            ->assertDontSee('This domain is not in the inventory.');
    }

    public function test_missing_domain_shows_the_inventory_message(): void
    {
        $this->actingAs($this->createAdmin(), 'admin');

        $this->get(route('admin.domain.details', ['domain' => 'missing.test']))
            ->assertOk()
            ->assertSee('This domain is not in the inventory.')
            ->assertDontSee('secret-domain-api-key-9f3a');
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super-'.uniqid(),
            'email' => 'super-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => Admin::SUPER_ADMIN,
        ]);
    }
}
