<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visitors coming through Cloudflare (tunnel or proxy) reach Nginx over plain
 * http. When Cloudflare says the visitor used https, generate https links.
 * Opening the site on the LAN via http://<ip> keeps working with http links.
 */
class DetectHttpsProxy
{
    public function handle(Request $request, Closure $next): Response
    {
        $viaHttps = $request->isSecure() // X-Forwarded-Proto: https from a trusted proxy
            || str_contains((string) $request->header('CF-Visitor'), '"https"');

        if ($viaHttps) {
            URL::forceScheme('https');
            $request->server->set('HTTPS', 'on'); // so secure cookies / redirects agree
        }

        return $next($request);
    }
}
