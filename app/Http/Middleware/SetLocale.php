<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The application is in French only (the language switcher was removed on 2026-09-28 at the owner's request).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale('fr');

        return $next($request);
    }
}
