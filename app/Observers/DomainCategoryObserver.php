<?php

namespace App\Observers;

use App\Models\Admin\DomainCategory;
use App\Services\LocalClientRateListSeedService;

class DomainCategoryObserver
{
    public function __construct(
        private LocalClientRateListSeedService $seedService,
    ) {}

    public function created(DomainCategory $category): void
    {
        $this->seedService->seedForNewDomainCategory($category);
    }
}
