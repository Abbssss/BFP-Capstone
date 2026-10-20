<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use Closure;
use Illuminate\Http\Request;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $module): mixed
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (! app(RolePermissionService::class)->allows($user->role ?? 'maintenance', $module)) {
            abort(403, 'You do not have access to this resource.');
        }

        return $next($request);
    }
}
