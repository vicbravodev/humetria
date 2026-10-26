<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags every log line, audit entry and queued job of the request with a
 * server-generated request id and the acting user, so one action can be
 * traced end to end. Context propagates to queued jobs automatically.
 */
final class AssignRequestContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = Str::uuid()->toString();

        Context::add('request_id', $requestId);

        if ($request->user() !== null) {
            Context::add('user_id', $request->user()->getAuthIdentifier());
        }

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
