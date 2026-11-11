<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts unique visitors (by IP) on the public website and stores their IP,
 * location (from Cloudflare) and browser so they can be seen in the admin panel.
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
                $this->record($request);
            } catch (Throwable $e) {
                report($e); // never break the page because of stats
            }
        }

        return $response;
    }

    protected function record(Request $request): void
    {
        $now = now();
        $ip = $this->clientIp($request);
        $cf = $this->viaLocalProxy($request);

        // Location headers are added by Cloudflare: CF-IPCountry by default, city and
        // region with the "Add visitor location headers" managed transform.
        $location = [
            'country' => $cf ? $this->header($request, 'CF-IPCountry', 2) : null,
            'region' => $cf ? $this->header($request, 'CF-Region', 100) : null,
            'city' => $cf ? $this->header($request, 'CF-IPCity', 100) : null,
        ];

        // Only external referrers ("came from google.com"), not our own pages.
        $referer = (string) $request->headers->get('referer');
        $refHost = (string) parse_url($referer, PHP_URL_HOST);
        $ownHost = preg_replace('/^www\./', '', $request->getHost());
        $referrer = $refHost !== '' && strcasecmp(preg_replace('/^www\./', '', $refHost), $ownHost) !== 0
            ? mb_substr($referer, 0, 255)
            : null;

        $details = [
            'ip' => $ip,
            'last_path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'updated_at' => $now,
        ] + array_filter($location);

        DB::table('visits')->upsert(
            [[
                'ip_hash' => hash_hmac('sha256', $this->visitorKey($ip), (string) config('app.key')),
                'date' => $now->toDateString(),
                'hits' => 1,
                'country' => $location['country'],
                'region' => $location['region'],
                'city' => $location['city'],
                'referrer' => $referrer,
                'created_at' => $now,
            ] + $details],
            ['ip_hash', 'date'],
            ['hits' => DB::raw('hits + 1')] + $details,
        );
    }

    /** True when the request reached us through cloudflared / Nginx on this machine or the LAN. */
    protected function viaLocalProxy(Request $request): bool
    {
        $remote = (string) $request->server('REMOTE_ADDR');

        return $remote === ''
            || filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * The visitor's real public IP. Behind Cloudflare, CF-Connecting-IP is the client
     * IP (X-Forwarded-For can contain extra hops that change between requests).
     */
    protected function clientIp(Request $request): string
    {
        $cfIp = trim((string) $request->header('CF-Connecting-IP'));

        $ip = $cfIp !== '' && $this->viaLocalProxy($request) && filter_var($cfIp, FILTER_VALIDATE_IP)
            ? $cfIp
            : (string) $request->ip();

        // IPv4-mapped IPv6 (::ffff:1.2.3.4) → plain IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
                return inet_ntop(substr($packed, 12));
            }
        }

        return $ip;
    }

    /**
     * What makes a visitor unique. IPv6 devices rotate "privacy" addresses
     * constantly, so IPv6 is grouped by its /64 network prefix.
     */
    protected function visitorKey(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return inet_ntop(substr(inet_pton($ip), 0, 8).str_repeat("\0", 8)).'/64';
        }

        return $ip;
    }

    protected function header(Request $request, string $name, int $max): ?string
    {
        $value = trim((string) $request->header($name));

        return $value === '' ? null : mb_substr($value, 0, $max);
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
