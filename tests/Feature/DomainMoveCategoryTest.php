<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainMoveCategoryTest extends TestCase
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
            $table->unsignedBigInteger('domain_category_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->integer('status')->default(0);
            $table->text('api_key')->nullable();
            $table->string('api_key_lookup_hash')->nullable();
            $table->timestamps();
        });
    }

    public function test_form_page_loads(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        DomainCategory::query()->create(['name' => 'Old Cat', 'slug' => 'old-cat']);

        $this->get(route('admin.domain.move-category'))
            ->assertOk()
            ->assertSee('Old category')
            ->assertSee('New category')
            ->assertSee('Old Cat');
    }

    public function test_moves_all_domains_from_source_to_target_category(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $old = DomainCategory::query()->create(['name' => 'Gaming', 'slug' => 'gaming']);
        $new = DomainCategory::query()->create(['name' => 'News', 'slug' => 'news']);
        $other = DomainCategory::query()->create(['name' => 'Other', 'slug' => 'other']);

        Domain::query()->create(['name' => 'a.example', 'domain_category_id' => $old->id, 'admin_id' => $admin->id, 'status' => 1]);
        Domain::query()->create(['name' => 'b.example', 'domain_category_id' => $old->id, 'admin_id' => $admin->id, 'status' => 0]);
        Domain::query()->create(['name' => 'c.example', 'domain_category_id' => $other->id, 'admin_id' => $admin->id, 'status' => 1]);

        $this->post(route('admin.domain.move-category.process'), [
            'source_category_id' => $old->id,
            'target_category_id' => $new->id,
        ])
            ->assertRedirect(route('admin.domain.index', ['category_id' => $new->id]))
            ->assertSessionHas('cus__success');

        $this->assertSame(0, Domain::query()->where('domain_category_id', $old->id)->count());
        $this->assertSame(2, Domain::query()->where('domain_category_id', $new->id)->count());
        $this->assertSame(1, Domain::query()->where('domain_category_id', $other->id)->count());
    }

    public function test_rejects_same_source_and_target(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $cat = DomainCategory::query()->create(['name' => 'Same', 'slug' => 'same']);

        $this->post(route('admin.domain.move-category.process'), [
            'source_category_id' => $cat->id,
            'target_category_id' => $cat->id,
        ])->assertSessionHasErrors('target_category_id');
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
