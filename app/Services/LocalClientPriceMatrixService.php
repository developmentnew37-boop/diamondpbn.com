<?php

namespace App\Services;

use App\Models\Admin\DomainCategory;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientDomainCategoryPrice;
use App\Models\Admin\LocalClientRateList;
use App\Models\Admin\LocalClientRateListPrice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
     * @return Collection<int, array{
     *   domain_category_id: int,
     *   category_name: string,
     *   post_price: ?string,
     *   sidebar_price: ?string,
     *   hidden_links_price: ?string,
     *   sticky_price: ?string
     * }>
     */
    public function buildGridForRateList(?LocalClientRateList $rateList = null): Collection
    {
        $categories = DomainCategory::query()->orderBy('name')->get(['id', 'name']);
        $existing = $rateList
            ? $rateList->prices()->get()->keyBy('domain_category_id')
            : collect();

        return $categories->map(function (DomainCategory $category) use ($existing) {
            /** @var LocalClientRateListPrice|null $row */
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
     * Matrix rows as JSON-friendly arrays keyed by domain_category_id (for copy AJAX).
     *
     * @return array<int, array{post_price: ?string, sidebar_price: ?string, hidden_links_price: ?string, sticky_price: ?string}>
     */
    public function pricesKeyedByCategory(LocalClient $client): array
    {
        $out = [];
        foreach ($this->buildGrid($client) as $row) {
            $out[(int) $row['domain_category_id']] = [
                'post_price' => $row['post_price'],
                'sidebar_price' => $row['sidebar_price'],
                'hidden_links_price' => $row['hidden_links_price'],
                'sticky_price' => $row['sticky_price'],
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{post_price: ?string, sidebar_price: ?string, hidden_links_price: ?string, sticky_price: ?string}>
     */
    public function rateListPricesKeyedByCategory(LocalClientRateList $rateList): array
    {
        $out = [];
        foreach ($this->buildGridForRateList($rateList) as $row) {
            $out[(int) $row['domain_category_id']] = [
                'post_price' => $row['post_price'],
                'sidebar_price' => $row['sidebar_price'],
                'hidden_links_price' => $row['hidden_links_price'],
                'sticky_price' => $row['sticky_price'],
            ];
        }

        return $out;
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

    /**
     * @param  array<int, array<string, mixed>>  $matrixRows  keyed by domain_category_id
     */
    public function upsertRateListPrices(LocalClientRateList $rateList, array $matrixRows): void
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
                LocalClientRateListPrice::query()
                    ->where('rate_list_id', $rateList->id)
                    ->where('domain_category_id', $categoryId)
                    ->delete();

                continue;
            }

            LocalClientRateListPrice::updateOrCreate(
                [
                    'rate_list_id' => $rateList->id,
                    'domain_category_id' => $categoryId,
                ],
                $payload
            );
        }
    }

    public function clearClientPrices(LocalClient $client): void
    {
        LocalClientDomainCategoryPrice::query()
            ->where('local_client_id', $client->id)
            ->delete();
    }

    /**
     * Require every domain category to have all four campaign prices (0.00 allowed).
     *
     * @param  array<int|string, array<string, mixed>>  $matrixRows
     */
    public function validateCompleteMatrix(array $matrixRows): void
    {
        $categories = DomainCategory::query()->orderBy('name')->get(['id', 'name']);
        $fields = [
            'post_price' => 'Post',
            'sidebar_price' => 'Sidebar',
            'hidden_links_price' => 'Hidden links',
            'sticky_price' => 'Sticky',
        ];

        $errors = [];

        foreach ($categories as $category) {
            $row = $matrixRows[$category->id] ?? $matrixRows[(string) $category->id] ?? [];

            foreach ($fields as $field => $label) {
                $value = $row[$field] ?? null;
                $key = "prices.{$category->id}.{$field}";

                if ($value === null || $value === '') {
                    $errors[$key] = "{$label} price is required for \"{$category->name}\".";

                    continue;
                }

                if (! is_numeric($value) || (float) $value < 0) {
                    $errors[$key] = "{$label} price for \"{$category->name}\" must be a number of 0 or greater.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
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
