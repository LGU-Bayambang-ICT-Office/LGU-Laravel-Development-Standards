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
 * Middleware EnforceSessionTimeout
 *
 * Automatically logs out idle municipal staff sessions after a period of inactivity
 * (default: 15 minutes), mitigating unauthorized access on unattended government workstations.
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $timeoutMinutes = (int) (Config::get('lgu.dpa.idle_timeout_minutes')
                ?? Config::get('auth.session_timeout_minutes')
                ?? 15);

            $lastActivity = Session::get('last_activity_time');

            if ($lastActivity && (time() - $lastActivity > ($timeoutMinutes * 60))) {
                Auth::logout();
                Session::flush();

                return redirect()->route('login')->withErrors([
                    'session' => "Your session timed out due to {$timeoutMinutes} minutes of inactivity. Please log in again.",
                ]);
            }

            Session::put('last_activity_time', time());
        }

        return $next($request);
    }
}
