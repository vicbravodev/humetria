<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Of the public pages of the channel: nothing is kept (no-store), nothing is sent along (no referrer) and nobody indexes
 * them. SecurityHeaders keeps a header the response already carries, so these win over its defaults.
 */
final class PrivateChannelResponse
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
