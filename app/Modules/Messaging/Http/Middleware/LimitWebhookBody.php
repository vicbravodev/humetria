<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A webhook body over `messaging.webhook_max_bytes` is refused with 413 before anything parses it.
 */
final class LimitWebhookBody
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $max = config()->integer('messaging.webhook_max_bytes');

        if ((int) $request->headers->get('Content-Length') > $max || strlen($request->getContent()) > $max) {
            abort(413);
        }

        return $next($request);
    }
}
