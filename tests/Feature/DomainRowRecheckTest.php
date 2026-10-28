<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Domain;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainRowRecheckTest extends TestCase
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

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->integer('status')->default(0);
            $table->text('api_key')->nullable();
            $table->string('api_key_lookup_hash')->nullable();
            $table->string('agent_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_status_code')->nullable();
            $table->string('last_status_probe')->nullable();
            $table->text('last_status_message')->nullable();
            $table->timestamps();
        });
    }

    public function test_online_probe_marks_the_domain_connected(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');
        $domain = Domain::query()->create([
            'name' => 'online.example',
            'admin_id' => $admin->id,
            'status' => 0,
        ]);

        Http::fake([
            '*' => Http::response([
                'status' => true,
                'message' => 'Connected',
                'plugin_version' => '8.2.0',
                'plugin_manager_supported' => true,
            ], 200),
        ]);

        $this->postJson(route('admin.domain.recheck', $domain))
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('connected', true);

        $fresh = $domain->fresh();
        $this->assertSame(1, $fresh->status);
        $this->assertSame('Connected', $fresh->last_status_message);
    }

    public function test_public_508_is_kept_when_the_api_key_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');
        $domain = Domain::query()->create([
            'name' => 'limited.example',
            'admin_id' => $admin->id,
            'status' => 1,
            'api_key' => 'stored-key',
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/status/check')) {
                return Http::response([
                    'code' => 'bad_key',
                    'message' => 'Invalid API key',
                ], 403);
            }

            return Http::response('<html><title>508 Resource Limit Is Reached</title></html>', 508);
        });

        $this->postJson(route('admin.domain.recheck', $domain))
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('connected', false)
            ->assertJsonPath('message', 'HTTP 508: Resource Limit Is Reached');

        $fresh = $domain->fresh();
        $this->assertSame(0, $fresh->status);
        $this->assertSame('HTTP 508: Resource Limit Is Reached', $fresh->last_status_message);
    }

    public function test_unreadable_api_key_still_returns_json_and_saves_the_public_probe(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');
        $domain = Domain::query()->create([
            'name' => 'corrupt-key.example',
            'admin_id' => $admin->id,
            'status' => 0,
        ]);

        DB::table('domains')->where('id', $domain->id)->update([
            'api_key' => base64_encode(json_encode([
                'iv' => base64_encode('1234567890123456'),
                'value' => base64_encode('cipher'),
                'mac' => 'not-a-valid-mac',
            ])),
        ]);

        Http::fake([
            '*' => Http::response([
                'status' => true,
                'message' => 'Connected',
                'plugin_version' => '8.2.0',
                'plugin_manager_supported' => true,
            ], 200),
        ]);

        $this->postJson(route('admin.domain.recheck', $domain))
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('connected', true);

        $this->assertSame(1, $domain->fresh()->status);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $admin = $this->createAdmin();
        $domain = Domain::query()->create([
            'name' => 'guest.example',
            'admin_id' => $admin->id,
            'status' => 1,
        ]);

        $this->postJson(route('admin.domain.recheck', $domain))
            ->assertRedirect(route('admin.login'));

        $this->assertSame(1, $domain->fresh()->status);
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
