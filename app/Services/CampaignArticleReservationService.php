<?php

namespace App\Services;

use App\Exceptions\InsufficientCampaignArticlesException;
use App\Models\Admin\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class CampaignArticleReservationService
{
    /**
     * @return array{article_category_id?: int|null, language_id?: int|null, article_set_id?: int|null}
     */
    public static function contextFromRequest(\Illuminate\Http\Request $request): array
    {
        return [
            'article_category_id' => $request->filled('article_niche') ? (int) $request->article_niche : null,
            'language_id' => $request->filled('language_articles_id') ? (int) $request->input('language_articles_id') : null,
            'article_set_id' => $request->filled('own-articles') ? (int) $request->input('own-articles') : null,
        ];
    }

    /**
     * Reserve an article for a campaign slot, substituting from the same pool when the
     * requested article is missing, locked, or already used by another campaign.
     *
     * @param  array{article_category_id?: int|null, language_id?: int|null, article_set_id?: int|null}  $context
     * @param  array<int, int>  $excludeArticleIds  Already reserved in this campaign create request
     * @return array{article: Article, substituted: bool, requested_id: int}
     */
    public function reserve(
        int $requestedArticleId,
        string $selectionMode,
        array $context,
        array $excludeArticleIds = [],
        bool $markStatusUsed = true,
    ): array {
        $excludeArticleIds = array_values(array_unique(array_map('intval', $excludeArticleIds)));

        $requested = Article::query()
            ->where('id', $requestedArticleId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($requested && $this->isAvailable($requested) && ! in_array($requested->id, $excludeArticleIds, true)) {
            $this->lockArticle($requested, $markStatusUsed);

            return [
                'article' => $requested->fresh(),
                'substituted' => false,
                'requested_id' => $requestedArticleId,
            ];
        }

        $hint = $requested ?? Article::query()->find($requestedArticleId);
        $replacement = $this->findReplacement($selectionMode, $context, $hint, $excludeArticleIds);

        if (! $replacement) {
            throw new InsufficientCampaignArticlesException(
                $this->buildInsufficientMessage($selectionMode, $context, $requestedArticleId),
                1,
                0,
            );
        }

        $this->lockArticle($replacement, $markStatusUsed);

        Log::info('Campaign article auto-substituted', [
            'requested_article_id' => $requestedArticleId,
            'replacement_article_id' => $replacement->id,
            'selection_mode' => $selectionMode,
            'context' => $context,
        ]);

        return [
            'article' => $replacement->fresh(),
            'substituted' => true,
            'requested_id' => $requestedArticleId,
        ];
    }

    private function isAvailable(Article $article): bool
    {
        return $article->lock_at === null
            && (int) $article->status === Article::STATUS_UNUSED;
    }

    private function lockArticle(Article $article, bool $markStatusUsed): void
    {
        $payload = ['lock_at' => now()];

        if ($markStatusUsed) {
            $payload['status'] = Article::STATUS_USED;
        }

        $article->update($payload);
    }

    /**
     * @param  array{article_category_id?: int|null, language_id?: int|null, article_set_id?: int|null}  $context
     * @param  array<int, int>  $excludeArticleIds
     */
    private function findReplacement(
        string $selectionMode,
        array $context,
        ?Article $hintArticle,
        array $excludeArticleIds,
    ): ?Article {
        $exclude = array_values(array_unique(array_merge(
            $excludeArticleIds,
            $hintArticle ? [$hintArticle->id] : [],
        )));

        return $this->buildPoolQuery($selectionMode, $context, $hintArticle, $exclude)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array{article_category_id?: int|null, language_id?: int|null, article_set_id?: int|null}  $context
     * @param  array<int, int>  $excludeArticleIds
     */
    private function buildPoolQuery(
        string $selectionMode,
        array $context,
        ?Article $hintArticle,
        array $excludeArticleIds,
    ): Builder {
        $query = Article::query()
            ->where('status', Article::STATUS_UNUSED)
            ->whereNull('deleted_at')
            ->whereNull('lock_at')
            ->whereNotIn('id', $excludeArticleIds);

        match ($selectionMode) {
            'language_article' => $this->applyLanguageScope($query, $context, $hintArticle),
            'own_article' => $this->applyArticleSetScope($query, $context),
            default => $this->applyCategoryScope($query, $context, $hintArticle),
        };

        return $query;
    }

    /**
     * @param  array{article_category_id?: int|null}  $context
     */
    private function applyCategoryScope(Builder $query, array $context, ?Article $hintArticle): void
    {
        $categoryId = $context['article_category_id'] ?? $hintArticle?->article_category_id;

        if ($categoryId) {
            $query->where('article_category_id', $categoryId);
        }
    }

    /**
     * @param  array{language_id?: int|null}  $context
     */
    private function applyLanguageScope(Builder $query, array $context, ?Article $hintArticle): void
    {
        $languageId = $context['language_id'] ?? $hintArticle?->article_language_id;

        if ($languageId) {
            $query->where('article_language_id', $languageId);
        }
    }

    /**
     * @param  array{article_set_id?: int|null}  $context
     */
    private function applyArticleSetScope(Builder $query, array $context): void
    {
        $setId = $context['article_set_id'] ?? null;

        if ($setId) {
            $query->whereHas('articleSets', fn (Builder $q) => $q->where('article_sets.id', $setId));
        }
    }

    /**
     * @param  array{article_category_id?: int|null, language_id?: int|null, article_set_id?: int|null}  $context
     */
    private function buildInsufficientMessage(string $selectionMode, array $context, int $requestedArticleId): string
    {
        $scope = match ($selectionMode) {
            'language_article' => 'the selected language',
            'own_article' => 'the selected article set',
            default => 'the selected article category',
        };

        return "Could not reserve article #{$requestedArticleId}: no unused articles remain in {$scope}. "
            .'Another user may have just used the remaining articles. Reduce post quantity or try again.';
    }
}
