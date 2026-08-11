<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Admin\LocalClient;
use Illuminate\Support\Collection;

trait ProvidesLocalClientsForForms
{
    /** @return Collection<int, LocalClient> */
    protected function activeLocalClientsForForms(): Collection
    {
        return LocalClient::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'default_currency']);
    }
}
