<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visitors coming through Cloudflare (tunnel or proxy) reach Nginx over plain http.
 *
 *  - Cloudflare visitor on http      → 301 to https
 *  - www.example.com                 → 301 to example.com
 *  - Cloudflare visitor on https     → generate https links
 *  - LAN visit via http://<ip>       → untouched, plain http links
 */
class DetectHttpsProxy
{
    public function handle(Request $request, Closure $next): Response
    {
        $cfVisitor = (string) $request->header('CF-Visitor');
        $viaCloudflare = $cfVisitor !== '' || $request->hasHeader('CF-Connecting-IP');
        $viaHttps = $request->isSecure() || str_contains($cfVisitor, '"https"');
        $host = $request->getHost();
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if ($viaCloudflare && ! $isIp && $request->isMethodSafe()) {
            $canonicalHost = preg_replace('/^www\./', '', $host);

            if (! $viaHttps || $canonicalHost !== $host) {
                return redirect()->away('https://'.$canonicalHost.$request->getRequestUri(), 301);
            }
        }

        if ($viaHttps) {
            URL::forceScheme('https');
            $request->server->set('HTTPS', 'on'); // so secure cookies / redirects agree
        }

        return $next($request);
    }
}
