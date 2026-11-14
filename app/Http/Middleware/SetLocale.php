<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the website in the visitor's chosen language (Danish by default).
 *
 * The choice comes from ?lang=xx, the language switcher (session) or the
 * long-lived "locale" cookie, in that order.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('lang');

        if (Locales::isSupported($locale)) {
            static::remember($locale);
        } else {
            $locale = $request->hasSession() ? $request->session()->get(Locales::SESSION_KEY) : null;
            $locale ??= $request->cookie(Locales::COOKIE);
        }

        app()->setLocale(Locales::isSupported($locale) ? $locale : Locales::default());

        return $next($request);
    }

    /** Store the visitor's language for this session and future visits. */
    public static function remember(string $locale): void
    {
        if (request()->hasSession()) {
            request()->session()->put(Locales::SESSION_KEY, $locale);
        }
        Cookie::queue(Locales::COOKIE, $locale, 60 * 24 * 365);
    }
}
