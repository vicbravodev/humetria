<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app's own page for the errors a person can meet (pantallas: Error-403, Error-404), in place of the framework's
 * bare one. The status code never changes: a 404 that hides another company's record stays a 404, and a 429 still says
 * when to try again. JSON requests keep their JSON, and a server error keeps the debug page while debugging. On the
 * survey the page speaks "usted" to the person answering it.
 */
final class ErrorPage
{
    private const array SHOWN = [403, 404, 419, 429, 500, 503];

    /** What a throttled response says about trying again, kept on the page that replaces it. */
    private const array KEPT_HEADERS = ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'];

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

        $page = Inertia::render('error', ['status' => $status, 'survey' => $request->routeIs('survey.*')])
            ->toResponse($request)
            ->setStatusCode($status);

        foreach (self::KEPT_HEADERS as $header) {
            if ($response->headers->has($header)) {
                $page->headers->set($header, $response->headers->get($header));
            }
        }

        // The router prepares what a route returns; nothing prepares what the exception handler answers.
        return $page->prepare($request);
    }
}
