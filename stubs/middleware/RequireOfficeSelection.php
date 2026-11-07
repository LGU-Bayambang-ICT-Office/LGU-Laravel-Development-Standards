<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware RequireOfficeSelection
 *
 * Ensures municipal users assigned to multiple offices/departments explicitly select
 * their active working office context before accessing office-scoped transactional data.
 */
class RequireOfficeSelection
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && session()->get('requires_office_selection') === true) {
            $allowedRoutes = ['office.select', 'office.select.store', 'office.switch', 'logout'];

            if (! $request->routeIs($allowedRoutes)) {
                return redirect()->route('office.select');
            }
        }

        return $next($request);
    }
}
