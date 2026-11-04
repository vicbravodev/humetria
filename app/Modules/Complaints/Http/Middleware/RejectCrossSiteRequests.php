<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public routes have no forgery token of a session to rely on, so a request the browser says comes from another site is
 * refused, as the session's own check would.
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
