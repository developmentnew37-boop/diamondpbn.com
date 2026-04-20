<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesSuperAdminCampaignOwnerFilter
{
    /**
     * For Super Admins: default to own campaigns; use ?filter_user=all or ?filter_user={admin id} to widen.
     * For other roles: always scoped to own rows; filter_user in query is rejected.
     *
     * @return array{showCampaignOwnerFilter: bool, campaignOwnerFilter: string|null, campaignOwnerUsers: \Illuminate\Support\Collection|null}
     */
    protected function scopeCampaignQueryForOwner(Builder $query, Request $request, Admin $admin): array
    {
        if (!$admin->isSuperAdmin()) {
            if ($request->filled('filter_user')) {
                abort(400);
            }
            $query->where('admin_id', $admin->id);

            return [
                'showCampaignOwnerFilter' => false,
                'campaignOwnerFilter' => null,
                'campaignOwnerUsers' => null,
            ];
        }

        $raw = $request->query('filter_user');

        if ($raw !== null && $raw !== '' && $raw !== 'all') {
            if (! ctype_digit((string) $raw) || ! Admin::whereKey((int) $raw)->exists()) {
                abort(404);
            }
        }

        $users = Admin::query()->orderBy('name')->get(['id', 'name', 'email']);

        if ($raw === null || $raw === '') {
            $query->where('admin_id', $admin->id);

            return [
                'showCampaignOwnerFilter' => true,
                'campaignOwnerFilter' => 'mine',
                'campaignOwnerUsers' => $users,
            ];
        }

        if ($raw === 'all') {
            return [
                'showCampaignOwnerFilter' => true,
                'campaignOwnerFilter' => 'all',
                'campaignOwnerUsers' => $users,
            ];
        }

        $query->where('admin_id', (int) $raw);

        return [
            'showCampaignOwnerFilter' => true,
            'campaignOwnerFilter' => (string) (int) $raw,
            'campaignOwnerUsers' => $users,
        ];
    }
}
