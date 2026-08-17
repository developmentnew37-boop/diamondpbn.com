<?php

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckFeaturePermission
{
    /**
     * Usage: middleware('permission:local_clients.manage')
     * Super Admin always passes via Admin::hasPermission().
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login')
                ->with('cus__error', 'Please login to access this page');
        }

        if (! $admin->hasPermission($permission)) {
            return redirect()->route('admin.dashboard')
                ->with('cus__error', 'You do not have permission to access this page');
        }

        return $next($request);
    }
}
