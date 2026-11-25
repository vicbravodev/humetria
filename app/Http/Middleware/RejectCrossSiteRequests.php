<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public routes have no forgery token of a session to rely on, so a request the browser says comes from another site is
 * refused, as the session's own check would. Shared by the routes without a session of any module (the channel of
 * complaints, the tickets and the delivery of marks made without a connection).
 */
final class RejectCrossSiteRequests
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->header('Sec-Fetch-Site') === 'cross-site', 403);

        return $next($request);
    }
}
