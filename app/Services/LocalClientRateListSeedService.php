<?php

namespace App\Services;

use App\Models\Admin\DomainCategory;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientDomainCategoryPrice;
use App\Models\Admin\LocalClientRateList;
use App\Models\Admin\LocalClientRateListPrice;

class LocalClientRateListSeedService
{
    private const ZERO_PRICES = [
        'post_price' => '0.00',
        'sidebar_price' => '0.00',
        'hidden_links_price' => '0.00',
        'sticky_price' => '0.00',
    ];

    /**
     * Seed price 0 for a newly created domain category on all rate lists
     * and all custom-rate clients (rate_list_id null).
     */
    public function seedForNewDomainCategory(DomainCategory $category): void
    {
        $categoryId = (int) $category->id;

        LocalClientRateList::query()->pluck('id')->each(function ($rateListId) use ($categoryId) {
            LocalClientRateListPrice::query()->firstOrCreate(
                [
                    'rate_list_id' => $rateListId,
                    'domain_category_id' => $categoryId,
                ],
                self::ZERO_PRICES
            );
        });

        LocalClient::query()
            ->whereNull('rate_list_id')
            ->pluck('id')
            ->each(function ($clientId) use ($categoryId) {
                LocalClientDomainCategoryPrice::query()->firstOrCreate(
                    [
                        'local_client_id' => $clientId,
                        'domain_category_id' => $categoryId,
                    ],
                    self::ZERO_PRICES
                );
            });
    }

    /**
     * Seed price 0 for every existing domain category on a newly created rate list.
     */
    public function seedForNewRateList(LocalClientRateList $rateList): void
    {
        DomainCategory::query()->pluck('id')->each(function ($categoryId) use ($rateList) {
            LocalClientRateListPrice::query()->firstOrCreate(
                [
                    'rate_list_id' => $rateList->id,
                    'domain_category_id' => (int) $categoryId,
                ],
                self::ZERO_PRICES
            );
        });
    }
}
