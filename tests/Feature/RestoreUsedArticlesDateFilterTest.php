<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\ArticleLanguage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RestoreUsedArticlesDateFilterTest extends TestCase
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

        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->boolean('viewed')->default(false);
            $table->timestamps();
        });

        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('article_languages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
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
    }

    protected function tearDown(): void
    {
        foreach ([
            'articles',
            'article_languages',
            'article_categories',
            'pending_domains',
            'admin_feature_permissions',
            'admins',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_restore_index_filters_by_deleted_at_date_range(): void
    {
        $admin = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($admin, 'admin');

        $this->createTrashedUsedArticle($admin->id, 'In Range Article', 'in-range', null, null, '2024-06-15 10:00:00');
        $this->createTrashedUsedArticle($admin->id, 'Too Old Article', 'too-old', null, null, '2024-01-10 10:00:00');
        $this->createTrashedUsedArticle($admin->id, 'Too New Article', 'too-new', null, null, '2024-12-01 10:00:00');

        $this->get(route('admin.article.restore-used.index', [
            'date_from' => '2024-06-01',
            'date_to' => '2024-06-30',
        ]))
            ->assertOk()
            ->assertSee('In Range Article')
            ->assertDontSee('Too Old Article')
            ->assertDontSee('Too New Article')
            ->assertSee('Used from')
            ->assertSee('Used to');
    }

    public function test_restore_index_combines_category_language_and_date_filters(): void
    {
        $admin = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($admin, 'admin');

        $gaming = ArticleCategory::query()->create(['name' => 'Gaming']);
        $sports = ArticleCategory::query()->create(['name' => 'Sports']);
        $english = ArticleLanguage::query()->create(['name' => 'English']);
        $spanish = ArticleLanguage::query()->create(['name' => 'Spanish']);

        $this->createTrashedUsedArticle(
            $admin->id,
            'Gaming English Match',
            'gaming-en',
            $gaming->id,
            $english->id,
            '2024-08-10 12:00:00'
        );
        $this->createTrashedUsedArticle(
            $admin->id,
            'Gaming Spanish Miss',
            'gaming-es',
            $gaming->id,
            $spanish->id,
            '2024-08-10 12:00:00'
        );
        $this->createTrashedUsedArticle(
            $admin->id,
            'Sports English Miss',
            'sports-en',
            $sports->id,
            $english->id,
            '2024-08-10 12:00:00'
        );
        $this->createTrashedUsedArticle(
            $admin->id,
            'Gaming English Out Of Range',
            'gaming-en-old',
            $gaming->id,
            $english->id,
            '2024-01-05 12:00:00'
        );

        $this->get(route('admin.article.restore-used.index', [
            'category' => $gaming->id,
            'language' => $english->id,
            'date_from' => '2024-08-01',
            'date_to' => '2024-08-31',
        ]))
            ->assertOk()
            ->assertSee('Gaming English Match')
            ->assertDontSee('Gaming Spanish Miss')
            ->assertDontSee('Sports English Miss')
            ->assertDontSee('Gaming English Out Of Range');
    }

    public function test_date_from_after_date_to_is_swapped(): void
    {
        $admin = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($admin, 'admin');

        $this->createTrashedUsedArticle($admin->id, 'Mid June Article', 'mid-june', null, null, '2024-06-15 10:00:00');
        $this->createTrashedUsedArticle($admin->id, 'January Article', 'january', null, null, '2024-01-10 10:00:00');

        $this->get(route('admin.article.restore-used.index', [
            'date_from' => '2024-06-30',
            'date_to' => '2024-06-01',
        ]))
            ->assertOk()
            ->assertSee('Mid June Article')
            ->assertDontSee('January Article');
    }

    private function createAdmin(int $type): Admin
    {
        return Admin::query()->create([
            'name' => $type === Admin::SUPER_ADMIN ? 'Super Admin' : 'Admin',
            'slug' => 'admin-'.uniqid(),
            'email' => 'admin-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => $type,
        ]);
    }

    private function createTrashedUsedArticle(
        int $adminId,
        string $name,
        string $slug,
        ?int $categoryId,
        ?int $languageId,
        string $deletedAt
    ): Article {
        $article = Article::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => 'Body for '.$name,
            'article_category_id' => $categoryId,
            'article_language_id' => $languageId,
            'type' => Article::TYPE_MANUAL,
            'status' => Article::STATUS_USED,
            'admin_id' => $adminId,
        ]);

        $article->delete();

        DB::table('articles')->where('id', $article->id)->update([
            'deleted_at' => $deletedAt,
        ]);

        return $article->fresh();
    }
}
