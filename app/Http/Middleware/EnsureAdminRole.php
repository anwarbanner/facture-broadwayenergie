<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('Admin')) {
            abort(403, 'Acces refuse : reserve aux administrateurs.');
        }

        return $next($request);
    }
}
