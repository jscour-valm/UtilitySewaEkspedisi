<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $utility = $user->userUtility;

        if (! $utility || ! in_array($utility->role, $roles)) {
            abort(403);
        }

        return $next($request);
    }
}
