<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep the whole site out of search engines (and their AI summaries).
 *
 * robots.txt must stay open: crawlers have to fetch a page to see this header,
 * otherwise an already-indexed URL can linger in results.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');

        return $response;
    }
}
