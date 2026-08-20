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

    /**
     * Filter by the same counter-derived status the list badges use.
     *
     * DB `status` can stay `queued` while posts are succeeding, so filtering on
     * that column alone mismatches the UI (Queued chip showing Running rows).
     */
    protected function applyCampaignListStatusFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('status')) {
            return;
        }

        $status = (string) $request->input('status');
        if (! in_array($status, $this->campaignListStatusFilterValues(), true)) {
            return;
        }

        match ($status) {
            'queued' => $query
                ->where('completed_targets', 0)
                ->where('failed_targets', 0)
                ->where(function (Builder $inner) {
                    $inner->where('total_targets', 0)
                        ->orWhereRaw('(completed_targets + failed_targets) < total_targets');
                }),
            // Compare sums — never subtract UNSIGNED columns (MySQL underflow if done > total).
            'running' => $query
                ->whereRaw('(completed_targets + failed_targets) < total_targets')
                ->where(function (Builder $inner) {
                    $inner->where('completed_targets', '>', 0)
                        ->orWhere('failed_targets', '>', 0)
                        ->orWhere('status', 'running');
                }),
            'completed' => $query
                ->where('total_targets', '>', 0)
                ->where('failed_targets', 0)
                ->whereColumn('completed_targets', 'total_targets'),
            'failed' => $query
                ->where('total_targets', '>', 0)
                ->where('completed_targets', 0)
                ->whereColumn('failed_targets', 'total_targets'),
            'semi_failed' => $query
                ->where('total_targets', '>', 0)
                ->where('completed_targets', '>', 0)
                ->where('failed_targets', '>', 0)
                ->whereRaw('(completed_targets + failed_targets) >= total_targets'),
            default => null,
        };
    }
}
