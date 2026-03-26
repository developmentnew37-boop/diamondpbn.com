<?php

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts campaign creation to Super Admin and Admin only.
 * Members can only add articles; they are not allowed to create campaigns.
 */
class CanCreateCampaigns
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return redirect()->route('admin.login')
                ->with('cus__error', 'Please login to access this page');
        }

        if (!$admin->canCreateCampaigns()) {
            return redirect()->route('admin.dashboard')
                ->with('cus__error', 'You do not have permission to create campaigns. Members can only add articles.');
        }

        return $next($request);
    }
}
