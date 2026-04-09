<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Support\Facades\Auth;

trait AuthorizesAdminCampaign
{
    protected function authorizeCampaignAccess(?object $model): void
    {
        if (!$model) {
            abort(404);
        }
        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            abort(403);
        }
        if (method_exists($admin, 'isSuperAdmin') && $admin->isSuperAdmin()) {
            return;
        }
        $ownerId = (int) ($model->admin_id ?? 0);
        if ($ownerId !== (int) $admin->id) {
            abort(403);
        }
    }
}
