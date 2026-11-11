<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the sign-up page when registration is turned off in Admin → Settings.
 */
class RegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! setting('registration_enabled', true)) {
            return redirect()->route('login')->with('status', 'New sign-ups are currently disabled.');
        }

        return $next($request);
    }
}
