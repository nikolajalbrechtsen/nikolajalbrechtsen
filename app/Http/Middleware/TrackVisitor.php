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
                        'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
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
