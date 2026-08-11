<?php

namespace App\Services;

use App\Models\Admin\DomainCategory;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientDomainCategoryPrice;
use Illuminate\Support\Collection;

class LocalClientPriceMatrixService
{
    /**
     * @return Collection<int, array{
     *   domain_category_id: int,
     *   category_name: string,
     *   post_price: ?string,
     *   sidebar_price: ?string,
     *   hidden_links_price: ?string,
     *   sticky_price: ?string
     * }>
     */
    public function buildGrid(?LocalClient $client = null): Collection
    {
        $categories = DomainCategory::query()->orderBy('name')->get(['id', 'name']);
        $existing = $client
            ? $client->categoryPrices()->get()->keyBy('domain_category_id')
            : collect();

        return $categories->map(function (DomainCategory $category) use ($existing) {
            /** @var LocalClientDomainCategoryPrice|null $row */
            $row = $existing->get($category->id);

            return [
                'domain_category_id' => $category->id,
                'category_name' => $category->name,
                'post_price' => $row?->post_price,
                'sidebar_price' => $row?->sidebar_price,
                'hidden_links_price' => $row?->hidden_links_price,
                'sticky_price' => $row?->sticky_price,
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows  keyed by domain_category_id
     */
    public function upsertPrices(LocalClient $client, array $matrixRows): void
    {
        foreach ($matrixRows as $categoryId => $row) {
            $categoryId = (int) $categoryId;
            $payload = [
                'post_price' => $this->nullableDecimal($row['post_price'] ?? null),
                'sidebar_price' => $this->nullableDecimal($row['sidebar_price'] ?? null),
                'hidden_links_price' => $this->nullableDecimal($row['hidden_links_price'] ?? null),
                'sticky_price' => $this->nullableDecimal($row['sticky_price'] ?? null),
            ];

            if ($this->isEmptyPriceRow($payload)) {
                LocalClientDomainCategoryPrice::query()
                    ->where('local_client_id', $client->id)
                    ->where('domain_category_id', $categoryId)
                    ->delete();

                continue;
            }

            LocalClientDomainCategoryPrice::updateOrCreate(
                [
                    'local_client_id' => $client->id,
                    'domain_category_id' => $categoryId,
                ],
                $payload
            );
        }
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /** @param array<string, ?string> $payload */
    private function isEmptyPriceRow(array $payload): bool
    {
        foreach ($payload as $value) {
            if ($value !== null) {
                return false;
            }
        }

        return true;
    }
}
