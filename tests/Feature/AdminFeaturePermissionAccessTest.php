<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Article;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminFeaturePermissionAccessTest extends TestCase
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
            'admin_permissions' => [
                'articles.manage_all_trashed' => [
                    'label' => 'Manage all trashed used articles',
                    'description' => 'x',
                ],
                'local_clients.manage' => [
                    'label' => 'Manage local clients',
                    'description' => 'y',
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

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_normalized')->nullable();
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->longText('search_text')->nullable();
            $table->unsignedBigInteger('article_category_id')->nullable();
            $table->unsignedBigInteger('article_language_id')->nullable();
            $table->tinyInteger('type')->default(0);
            $table->tinyInteger('status')->default(0);
            $table->timestamp('lock_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('local_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('default_currency', 3)->default('USD');
            $table->string('billing_report_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id');
            $table->timestamps();
        });

        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->boolean('viewed')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('pending_domains');
        Schema::dropIfExists('local_clients');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('admin_feature_permissions');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    private function makeAdmin(int $type, string $suffix): Admin
    {
        return Admin::query()->create([
            'name' => "User {$suffix}",
            'slug' => "user-{$suffix}",
            'email' => "{$suffix}@example.com",
            'password' => 'secret',
            'type' => $type,
        ]);
    }

    public function test_admin_without_local_clients_permission_is_redirected(): void
    {
        $admin = $this->makeAdmin(Admin::ADMIN, 'no-lc');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.local-clients.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_with_local_clients_permission_can_open_index(): void
    {
        $admin = $this->makeAdmin(Admin::ADMIN, 'with-lc');
        $admin->syncFeaturePermissions(['local_clients.manage']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.local-clients.index'))
            ->assertOk();
    }

    public function test_admin_with_trashed_permission_can_queue_restore_for_foreign_article(): void
    {
        Queue::fake();

        $owner = $this->makeAdmin(Admin::ADMIN, 'owner');
        $actor = $this->makeAdmin(Admin::ADMIN, 'actor');
        $actor->syncFeaturePermissions(['articles.manage_all_trashed']);

        $article = Article::query()->create([
            'name' => 'Used article',
            'slug' => 'used-article',
            'description' => 'body',
            'status' => Article::STATUS_USED,
            'admin_id' => $owner->id,
        ]);
        $article->delete();

        $this->actingAs($actor, 'admin')
            ->post(route('admin.article.restore-used.restore', $article->id))
            ->assertRedirect();

        Queue::assertPushed(\App\Jobs\RestoreTrashedUsedArticlesJob::class, function ($job) use ($article, $actor) {
            return $job->articleIds === [(int) $article->id]
                && $job->adminUserId === (int) $actor->id
                && $job->canManageAll === true;
        });
    }

    public function test_admin_without_trashed_permission_cannot_restore_foreign_article(): void
    {
        Queue::fake();

        $owner = $this->makeAdmin(Admin::ADMIN, 'owner2');
        $actor = $this->makeAdmin(Admin::ADMIN, 'actor2');

        $article = Article::query()->create([
            'name' => 'Foreign used',
            'slug' => 'foreign-used',
            'description' => 'body',
            'status' => Article::STATUS_USED,
            'admin_id' => $owner->id,
        ]);
        $article->delete();

        $this->actingAs($actor, 'admin')
            ->post(route('admin.article.restore-used.restore', $article->id))
            ->assertRedirect();

        Queue::assertNothingPushed();
    }
}
