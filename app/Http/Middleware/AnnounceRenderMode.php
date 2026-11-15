<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\SsrPages;
use Closure;
use Illuminate\Http\Request;
use Inertia\Ssr\SsrState;
use Symfony\Component\HttpFoundation\Response;

/**
 * Says who rendered a page that Node may render: `X-Rendered-By: ssr` when Node's answer is in the page, `csr` when it is
 * not (Node failed, SSR is off, or someone is signed in). The check from outside reads it after each deploy. Nothing on
 * any other route.
 */
final class AnnounceRenderMode
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $routeName = $request->route()?->getName();

        if ($routeName !== null && isset(SsrPages::ALLOWED[$routeName])) {
            $response->headers->set('X-Rendered-By', app(SsrState::class)->response === null ? 'csr' : 'ssr');
        }

        return $response;
    }
}
