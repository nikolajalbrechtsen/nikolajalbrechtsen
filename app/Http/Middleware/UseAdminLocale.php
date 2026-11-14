<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel stays in English, whatever language the visitor picked
 * for the public website. Translations of content are edited per language
 * inside the admin forms instead.
 */
class UseAdminLocale
{
    public const LOCALE = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(self::LOCALE);

        return $next($request);
    }
}
