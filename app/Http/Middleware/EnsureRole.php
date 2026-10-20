<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $userRole = Auth::user()->role ?? 'maintenance';

        if (! in_array($userRole, $roles, true)) {
            abort(403, 'You do not have access to this resource.');
        }

        return $next($request);
    }
}
