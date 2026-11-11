<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts unique visitors (by IP) on the public website.
 * The IP is stored only as a keyed hash, never in plain text.
 */
class TrackVisitor
{
    /** Paths that are not "visits" to the website. */
    protected const IGNORE = ['admin', 'admin/*', 'livewire/*', '_code-auth', 'up', 'build/*', 'storage/*', 'auth/*'];

    protected const BOTS = '/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|httpclient|headless|lighthouse|facebookexternalhit/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            try {
                $now = now();
                DB::table('visits')->upsert(
                    [[
                        'ip_hash' => hash_hmac('sha256', $this->visitorKey($request), (string) config('app.key')),
                        'date' => $now->toDateString(),
                        'hits' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]],
                    ['ip_hash', 'date'],
                    ['hits' => DB::raw('hits + 1'), 'updated_at' => $now],
                );
            } catch (Throwable $e) {
                report($e); // never break the page because of stats
            }
        }

        return $response;
    }

    /**
     * The public IP that identifies a visitor.
     *
     *  - Behind Cloudflare, CF-Connecting-IP is the real client IP (X-Forwarded-For
     *    can contain extra hops that change between requests).
     *  - IPv6 devices rotate "privacy" addresses constantly, so IPv6 is grouped by
     *    its /64 network prefix — one household/connection = one visitor.
     */
    protected function visitorKey(Request $request): string
    {
        $ip = (string) $request->ip();
        $cfIp = trim((string) $request->header('CF-Connecting-IP'));

        // Only trust the header when the request came via a local proxy (cloudflared / Nginx).
        $remote = (string) $request->server('REMOTE_ADDR');
        $fromLocalProxy = $remote === '' || filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if ($cfIp !== '' && $fromLocalProxy && filter_var($cfIp, FILTER_VALIDATE_IP)) {
            $ip = $cfIp;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            // IPv4-mapped IPv6 (::ffff:1.2.3.4) → plain IPv4
            if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
                return inet_ntop(substr($packed, 12));
            }

            return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
        }

        return $ip;
    }

    protected function shouldTrack(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->expectsJson()
            && ! $request->is(...self::IGNORE)
            && $response->getStatusCode() < 400
            && ! preg_match(self::BOTS, (string) $request->userAgent());
    }
}
