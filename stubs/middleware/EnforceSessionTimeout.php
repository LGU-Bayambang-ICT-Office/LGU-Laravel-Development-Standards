<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $timeoutMinutes = config('auth.session_timeout_minutes', 15);
            $lastActivity = session('last_activity_time');

            if ($lastActivity && (time() - $lastActivity > ($timeoutMinutes * 60))) {
                Auth::logout();
                session()->flush();

                return redirect()->route('login')->withErrors([
                    'session' => 'Your session timed out due to 15 minutes of inactivity. Please log in again.',
                ]);
            }

            session(['last_activity_time' => time()]);
        }

        return $next($request);
    }
}
