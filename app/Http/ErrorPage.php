<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app's own page for the errors a person can meet (pantallas: Error-403, Error-404), in place of the framework's
 * bare one. The status code never changes: a 404 that hides another company's record stays a 404. JSON requests
 * keep their JSON, a 429 keeps errors/429, and a server error keeps the debug page while debugging.
 */
final class ErrorPage
{
    private const array SHOWN = [403, 404, 419, 500, 503];

    public function __construct(private HandleInertiaRequests $inertia) {}

    public function respond(Response $response, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! in_array($status, self::SHOWN, true) || $request->expectsJson() || ($status >= 500 && config('app.debug') === true)) {
            return $response;
        }

        // An address no route answers never ran the web middleware: share here what every page gets.
        if (Inertia::getShared('auth') === null) {
            Inertia::share($this->inertia->share($request));
        }

        return Inertia::render('error', ['status' => $status])
            ->toResponse($request)
            ->setStatusCode($status)
            // The router prepares what a route returns; nothing prepares what the exception handler answers.
            ->prepare($request);
    }
}
