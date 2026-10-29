<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags every log line, audit entry and queued job of the request with the acting user (and the
 * impersonator, if any), next to the request id AssignRequestId already set. Context propagates
 * to queued jobs automatically.
 */
final class AssignRequestContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            Context::add('user_id', $request->user()->getAuthIdentifier());
        }

        $impersonatorId = app(Impersonation::class)->impersonatorId();

        if ($impersonatorId !== null) {
            Context::add('impersonator_id', $impersonatorId);
        }

        return $next($request);
    }
}
