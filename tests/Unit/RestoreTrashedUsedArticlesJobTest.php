<?php

namespace Tests\Unit;

use App\Jobs\RestoreTrashedUsedArticlesJob;
use App\Models\Admin\Article;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RestoreTrashedUsedArticlesJobTest extends TestCase
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
            $table->tinyInteger('type')->default(2);
            $table->unsignedBigInteger('role_id')->nullable();
            $table->rememberToken();
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

    public function test_restores_trashed_used_article_to_unused_library(): void
    {
        DB::table('admins')->insert([
            'id' => 1,
            'name' => 'Admin',
            'slug' => 'admin',
            'email' => 'admin@test.com',
            'password' => 'secret',
            'type' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $article = Article::create([
            'name' => 'Used article',
            'slug' => 'used-article',
            'description' => 'Body',
            'type' => Article::TYPE_MANUAL,
            'status' => Article::STATUS_USED,
            'lock_at' => now(),
            'expires_at' => now()->addDay(),
            'admin_id' => 1,
        ]);
        $article->delete();

        $this->assertNotNull(Article::onlyTrashed()->find($article->id));

        (new RestoreTrashedUsedArticlesJob([$article->id], 1, true))->handle();

        $restored = Article::find($article->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertSame(Article::STATUS_UNUSED, (int) $restored->status);
        $this->assertNull($restored->lock_at);
        $this->assertNull($restored->expires_at);

        $inLibrary = Article::query()
            ->whereNull('lock_at')
            ->whereNull('deleted_at')
            ->where('status', '!=', Article::STATUS_USED)
            ->where('id', $article->id)
            ->exists();

        $this->assertTrue($inLibrary);
    }

    public function test_does_not_restore_articles_outside_admin_scope(): void
    {
        DB::table('admins')->insert([
            ['id' => 1, 'name' => 'A1', 'slug' => 'a1', 'email' => 'a1@test.com', 'password' => 'x', 'type' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'A2', 'slug' => 'a2', 'email' => 'a2@test.com', 'password' => 'x', 'type' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $article = Article::create([
            'name' => 'Other admin article',
            'slug' => 'other-admin',
            'description' => 'Body',
            'type' => Article::TYPE_MANUAL,
            'status' => Article::STATUS_USED,
            'admin_id' => 2,
        ]);
        $article->delete();

        (new RestoreTrashedUsedArticlesJob([$article->id], 1, false))->handle();

        $this->assertNotNull(Article::onlyTrashed()->find($article->id));
    }
}
