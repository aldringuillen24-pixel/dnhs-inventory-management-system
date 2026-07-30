<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect()->route('signin');
        }

        $user = Auth::user();

        if (!$user->role) {
            abort(403, 'Unauthorized. No role assigned.');
        }

        $userRole = strtolower($user->role->role_name);
        $allowedRoles = array_map(fn ($role) => strtolower($role), $roles);

        if (!in_array($userRole, $allowedRoles, true)) {
            abort(403, 'Unauthorized. Insufficient permissions.');
        }

        return $next($request);
    }
}