<?php

declare(strict_types=1);
namespace Lgu\GovSuite\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware RequireOfficeSelection
 *
 * Ensures municipal users assigned to multiple offices/departments explicitly select
 * their active working office context before accessing office-scoped transactional data.
 * Automatically synchronizes Spatie Permissions team context if spatie/laravel-permission is installed.
 */
class RequireOfficeSelection
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionKey = Config::get('lgu.office.session_key', 'active_office_id');
        $officeId = Session::get($sessionKey);

        if (Auth::check() && Session::get('requires_office_selection') === true) {
            $allowedRoutes = ['office.select', 'office.select.store', 'office.switch', 'logout'];

            if (! $request->routeIs($allowedRoutes)) {
                return redirect()->route('office.select');
            }
        }

        // Hydrate Spatie active team context to the user's active municipal office
        if ($officeId && function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($officeId);
        }

        return $next($request);
    }
}
