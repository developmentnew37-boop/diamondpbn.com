<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\Admin\AdminFeaturePermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminFeaturePermissionTest extends TestCase
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
            'admin_permissions' => [
                'articles.manage_all_trashed' => [
                    'label' => 'Manage all trashed used articles',
                    'description' => 'Restore or permanently delete soft-deleted used articles owned by any admin.',
                ],
                'local_clients.manage' => [
                    'label' => 'Manage local clients',
                    'description' => 'Create, edit, delete, and bill local clients (full Local Clients area).',
                ],
            ],
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
            $table->unique(['admin_id', 'permission']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('admin_feature_permissions');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    private function makeAdmin(int $type, string $suffix = 'a'): Admin
    {
        return Admin::query()->create([
            'name' => "User {$suffix}",
            'slug' => "user-{$suffix}",
            'email' => "user-{$suffix}@example.com",
            'password' => 'secret',
            'type' => $type,
        ]);
    }

    public function test_super_admin_has_every_known_permission(): void
    {
        $super = $this->makeAdmin(Admin::SUPER_ADMIN, 'super');

        $this->assertTrue($super->hasPermission('articles.manage_all_trashed'));
        $this->assertTrue($super->hasPermission('local_clients.manage'));
        $this->assertTrue($super->canManageAllTrashedArticles());
        $this->assertTrue($super->canManageLocalClients());
    }

    public function test_admin_without_grant_is_denied(): void
    {
        $admin = $this->makeAdmin(Admin::ADMIN, 'plain');

        $this->assertFalse($admin->hasPermission('articles.manage_all_trashed'));
        $this->assertFalse($admin->hasPermission('local_clients.manage'));
        $this->assertFalse($admin->canManageAllTrashedArticles());
        $this->assertFalse($admin->canManageLocalClients());
    }

    public function test_admin_with_grant_passes_helpers(): void
    {
        $admin = $this->makeAdmin(Admin::ADMIN, 'granted');
        $admin->syncFeaturePermissions([
            'articles.manage_all_trashed',
            'local_clients.manage',
        ]);

        $this->assertTrue($admin->fresh()->canManageAllTrashedArticles());
        $this->assertTrue($admin->fresh()->canManageLocalClients());
        $this->assertSame(
            ['articles.manage_all_trashed', 'local_clients.manage'],
            $admin->fresh()->permissionKeys()
        );
    }

    public function test_sync_rejects_unknown_keys_and_replaces_existing(): void
    {
        $admin = $this->makeAdmin(Admin::ADMIN, 'sync');
        $admin->syncFeaturePermissions(['articles.manage_all_trashed', 'not.a.real.key']);

        $this->assertSame(['articles.manage_all_trashed'], $admin->permissionKeys());
        $this->assertSame(1, AdminFeaturePermission::query()->where('admin_id', $admin->id)->count());

        $admin->syncFeaturePermissions(['local_clients.manage']);
        $this->assertSame(['local_clients.manage'], $admin->fresh()->permissionKeys());
        $this->assertFalse($admin->fresh()->canManageAllTrashedArticles());
        $this->assertTrue($admin->fresh()->canManageLocalClients());
    }

    public function test_member_cannot_receive_or_use_feature_permissions(): void
    {
        $member = $this->makeAdmin(Admin::MEMBER, 'member');
        $member->syncFeaturePermissions([
            'articles.manage_all_trashed',
            'local_clients.manage',
        ]);

        $this->assertSame([], $member->fresh()->permissionKeys());
        $this->assertFalse($member->fresh()->canManageAllTrashedArticles());
        $this->assertFalse($member->fresh()->canManageLocalClients());
        $this->assertSame(0, AdminFeaturePermission::query()->where('admin_id', $member->id)->count());
    }

    public function test_demoting_admin_to_member_clears_grants(): void
    {
        $admin = $this->makeAdmin(Admin::ADMIN, 'demote');
        $admin->syncFeaturePermissions(['local_clients.manage']);
        $this->assertTrue($admin->fresh()->canManageLocalClients());

        $admin->type = Admin::MEMBER;
        $admin->save();
        $admin->syncFeaturePermissions(['local_clients.manage']);

        $this->assertFalse($admin->fresh()->canManageLocalClients());
        $this->assertSame([], $admin->fresh()->permissionKeys());
    }

    public function test_sync_clears_stale_loaded_relation(): void
    {
        $admin = $this->makeAdmin(Admin::ADMIN, 'stale');
        $admin->load('featurePermissions');
        $this->assertFalse($admin->hasPermission('local_clients.manage'));

        $admin->syncFeaturePermissions(['local_clients.manage']);

        $this->assertTrue($admin->hasPermission('local_clients.manage'));
        $this->assertFalse($admin->relationLoaded('featurePermissions'));
    }

    public function test_only_one_super_admin_can_exist(): void
    {
        $this->makeAdmin(Admin::SUPER_ADMIN, 'super1');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only one Super Admin is allowed.');

        $this->makeAdmin(Admin::SUPER_ADMIN, 'super2');
    }

    public function test_cannot_promote_admin_to_super_admin_when_one_exists(): void
    {
        $this->makeAdmin(Admin::SUPER_ADMIN, 'super');
        $admin = $this->makeAdmin(Admin::ADMIN, 'plain');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only one Super Admin is allowed.');

        $admin->type = Admin::SUPER_ADMIN;
        $admin->save();
    }

    public function test_cannot_demote_the_super_admin(): void
    {
        $super = $this->makeAdmin(Admin::SUPER_ADMIN, 'super-keep');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The Super Admin role cannot be removed or demoted.');

        $super->type = Admin::ADMIN;
        $super->save();
    }
}
