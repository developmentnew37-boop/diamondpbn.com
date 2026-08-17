<?php

namespace App\Jobs;

use App\Models\Admin\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Restore soft-deleted articles marked "used" back to the normal unused library.
 * Pass articleIds = null to process every matching row for the admin scope.
 *
 * @param  int[]|null  $articleIds
 */
class RestoreTrashedUsedArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public ?array $articleIds,
        public int $adminUserId,
        public bool $canManageAll
    ) {
        $this->onQueue('article_restore');
    }

    public function handle(): void
    {
        $query = Article::onlyTrashed()
            ->where('articles.status', Article::STATUS_USED);

        if (! $this->canManageAll) {
            $query->where('articles.admin_id', $this->adminUserId);
        }

        if ($this->articleIds !== null) {
            $ids = array_values(array_unique(array_map('intval', $this->articleIds)));
            if ($ids === []) {
                return;
            }
            $query->whereIn('articles.id', $ids);
        }

        $restored = 0;
        $failed = 0;

        $query->orderBy('id')->chunkById(100, function ($articles) use (&$restored, &$failed) {
            foreach ($articles as $article) {
                try {
                    DB::transaction(function () use ($article) {
                        $article->restore();
                        $article->update([
                            'status' => Article::STATUS_UNUSED,
                            'lock_at' => null,
                            'expires_at' => null,
                        ]);
                    });
                    $restored++;
                } catch (Throwable $e) {
                    report($e);
                    $failed++;
                }
            }
        });

        if ($restored > 0 || $failed > 0) {
            Cache::forget('article_categories');
            Cache::forget('article_languages');
        }

        Log::info('RestoreTrashedUsedArticlesJob finished', [
            'restored' => $restored,
            'failed' => $failed,
            'scoped_ids' => $this->articleIds !== null,
            'admin_user_id' => $this->adminUserId,
            'can_manage_all' => $this->canManageAll,
        ]);
    }
}
