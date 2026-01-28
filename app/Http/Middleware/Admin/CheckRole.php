<?php

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin;

class CheckRole
{
    /**
     * Handle an incoming request.
     * 
     * Usage: middleware('role:0') or middleware('role:super_admin')
     * Multiple roles: middleware('role:0,1') for Super Admin OR Admin
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return redirect()->route('admin.login')
                ->with('cus__error', 'Please login to access this page');
        }

        // Convert role names to IDs if needed
        $allowedRoles = [];
        foreach ($roles as $role) {
            $allowedRoles[] = $this->getRoleId($role);
        }

        // Check if user has one of the allowed roles
        if (!in_array((int) $admin->type, $allowedRoles, true)) {
            return redirect()->route('admin.dashboard')
                ->with('cus__error', 'You do not have permission to access this page');
        }

        return $next($request);
    }

    /**
     * Convert role name to ID
     */
    private function getRoleId(string $role): int
    {
        return match (strtolower($role)) {
            'super_admin', 'superadmin', '0' => Admin::SUPER_ADMIN,
            'admin', '1' => Admin::ADMIN,
            'member', '2' => Admin::MEMBER,
            default => (int) $role,
        };
    }
}
