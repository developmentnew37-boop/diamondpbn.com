<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\ArticleLanguage;
use App\Models\Admin\ArticleSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UniqueArticleTitleSettingTest extends TestCase
{
    private ArticleCategory $category;

    private ArticleLanguage $language;

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
            $table->unique(['admin_id', 'permission']);
        });

        Schema::create('pending_domains', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
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

        Schema::create('article_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('require_unique_titles')->default(true);
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });

        $this->category = ArticleCategory::query()->create(['name' => 'General']);
        $this->language = ArticleLanguage::query()->create(['name' => 'English']);
    }

    protected function tearDown(): void
    {
        foreach ([
            'article_settings',
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

    public function test_duplicate_title_is_blocked_when_unique_titles_are_required(): void
    {
        $admin = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($admin, 'admin');
        $this->setUniqueTitles(true);
        $this->createArticle($admin->id, 'How to play GTA6 with more accurately');

        $this->post(route('admin.article.store'), $this->articlePayload('how to play gta6 with more accurately'))
            ->assertRedirect()
            ->assertSessionHas('cus__error', Article::duplicateTitleMessage());

        $this->assertSame(1, Article::query()->count());
    }

    public function test_soft_deleted_title_stays_blocked_until_permanently_deleted(): void
    {
        $admin = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($admin, 'admin');
        $this->setUniqueTitles(true);

        $article = $this->createArticle($admin->id, 'How to play GTA6 with more accurately');
        $article->delete();

        $this->post(route('admin.article.store'), $this->articlePayload('How to play GTA6 with more accurately'))
            ->assertSessionHas('cus__error', Article::duplicateTitleMessage());

        $article->forceDelete();

        $this->post(route('admin.article.store'), $this->articlePayload('How to play GTA6 with more accurately'))
            ->assertSessionHas('cus__success', 'Article created successfully.');

        $this->assertSame(1, Article::query()->count());
    }

    public function test_duplicate_title_is_allowed_when_unique_titles_are_off(): void
    {
        $admin = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($admin, 'admin');
        $this->setUniqueTitles(false);
        $this->createArticle($admin->id, 'How to play GTA6 with more accurately');

        $this->post(route('admin.article.store'), $this->articlePayload('How to play GTA6 with more accurately'))
            ->assertSessionHas('cus__success', 'Article created successfully.');

        $this->assertSame(2, Article::query()->count());
    }

    public function test_update_can_keep_same_title_but_cannot_steal_another(): void
    {
        $admin = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($admin, 'admin');
        $this->setUniqueTitles(true);

        $own = $this->createArticle($admin->id, 'Original title');
        $this->createArticle($admin->id, 'Taken title');

        $this->put(route('admin.article.update', $own->id), $this->articlePayload('Original title'))
            ->assertSessionHas('cus__success', 'Article updated successfully.');

        $this->put(route('admin.article.update', $own->id), $this->articlePayload('Taken title'))
            ->assertSessionHas('cus__error', Article::duplicateTitleMessage());

        $this->assertSame('Original title', $own->fresh()->name);
    }

    public function test_super_admin_can_toggle_setting_and_regular_admin_cannot(): void
    {
        $super = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($super, 'admin');

        $this->get(route('admin.article.index'))
            ->assertOk()
            ->assertSee('Require unique article titles')
            ->assertSee(route('admin.articles.unique-titles.update'), false);

        $this->post(route('admin.articles.unique-titles.update'), ['require_unique_titles' => '0'])
            ->assertRedirect(route('admin.article.index'))
            ->assertSessionHas('cus__success');

        $this->assertFalse(ArticleSetting::current()->fresh()->requiresUniqueTitles());

        $admin = $this->createAdmin(Admin::ADMIN);
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.article.index'))
            ->assertOk()
            ->assertSee('Require unique article titles')
            ->assertDontSee(route('admin.articles.unique-titles.update'));

        $this->post(route('admin.articles.unique-titles.update'), ['require_unique_titles' => '1'])
            ->assertForbidden();

        $this->assertFalse(ArticleSetting::current()->fresh()->requiresUniqueTitles());
    }

    public function test_manual_article_cannot_be_saved_without_content(): void
    {
        $admin = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($admin, 'admin');
        $this->setUniqueTitles(false);

        foreach (['', '<p></p>', '<p>&nbsp;</p>'] as $emptyBody) {
            $this->from(route('admin.article.create'))
                ->post(route('admin.article.store'), $this->articlePayload('Needs a body '.$emptyBody, $emptyBody))
                ->assertSessionHasErrors('description');
        }

        $this->assertSame(0, Article::query()->count());

        $this->post(route('admin.article.store'), $this->articlePayload('Has a real body'))
            ->assertSessionHas('cus__success', 'Article created successfully.');

        $this->assertSame(1, Article::query()->count());
    }

    public function test_manual_article_cannot_be_updated_to_empty_content(): void
    {
        $admin = $this->createAdmin(Admin::SUPER_ADMIN);
        $this->actingAs($admin, 'admin');
        $this->setUniqueTitles(false);

        $article = $this->createArticle($admin->id, 'Keep this title');

        $this->put(route('admin.article.update', $article->id), $this->articlePayload('Keep this title', '<p>&nbsp;</p>'))
            ->assertSessionHasErrors('description');

        $this->assertNotSame('', trim(strip_tags((string) $article->fresh()->description)));
    }

    private function createAdmin(int $type): Admin
    {
        return Admin::query()->create([
            'name' => $type === Admin::SUPER_ADMIN ? 'Super Admin' : 'Campaign Admin',
            'email' => 'admin-'.uniqid('', true).'@example.test',
            'password' => 'password',
            'type' => $type,
        ]);
    }

    private function setUniqueTitles(bool $on): void
    {
        ArticleSetting::current()->update(['require_unique_titles' => $on]);
    }

    private function createArticle(int $adminId, string $name): Article
    {
        return Article::query()->create([
            'name' => $name,
            'description' => 'Body for '.$name,
            'article_category_id' => $this->category->id,
            'article_language_id' => $this->language->id,
            'type' => Article::TYPE_MANUAL,
            'status' => Article::STATUS_UNUSED,
            'admin_id' => $adminId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function articlePayload(string $name, string $description = '<p>Article body</p>'): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'category' => $this->category->id,
            'language' => $this->language->id,
            'type' => Article::TYPE_MANUAL,
        ];
    }
}
