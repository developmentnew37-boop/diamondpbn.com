<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait ValidatesBulkCampaignIds
{
    /**
     * @return list<int>
     */
    protected function validatedBulkCampaignIds(Request $request): array
    {
        $request->validate([
            'campaign_ids'   => 'required|array',
            'campaign_ids.*' => 'integer|min:1',
        ]);

        return array_values(array_unique(array_filter(array_map('intval', $request->input('campaign_ids', [])))));
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  list<int>  $ids
     * @return list<int>
     */
    protected function campaignIdsOwnedByCurrentAdmin(array $ids, string $modelClass): array
    {
        if ($ids === []) {
            return [];
        }
        $admin = Auth::guard('admin')->user();
        $q = $modelClass::query()->whereIn('id', $ids);
        if ($admin && method_exists($admin, 'isSuperAdmin') && !$admin->isSuperAdmin()) {
            $q->where('admin_id', $admin->id);
        }

        return $q->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
