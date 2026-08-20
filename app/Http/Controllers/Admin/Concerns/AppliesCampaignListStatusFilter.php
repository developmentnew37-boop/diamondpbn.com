<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesCampaignListStatusFilter
{
    /**
     * Common campaign list statuses: queued, running, completed, failed, semi_failed.
     *
     * @return list<string>
     */
    protected function campaignListStatusFilterValues(): array
    {
        return ['queued', 'running', 'completed', 'failed', 'semi_failed'];
    }

    /**
     * Validation rule fragment for ?status= on campaign index pages.
     */
    protected function campaignListStatusValidationRule(): string
    {
        return 'nullable|in:'.implode(',', $this->campaignListStatusFilterValues());
    }

    protected function applyCampaignListStatusFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('status')) {
            return;
        }

        $status = (string) $request->input('status');
        if (! in_array($status, $this->campaignListStatusFilterValues(), true)) {
            return;
        }

        $query->where('status', $status);
    }
}
