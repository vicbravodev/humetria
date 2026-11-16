<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
    /** The pages whoever is not in the panel opens with their own link, spoken to as «usted». */
    public const array WORKER_ROUTES = ['survey.*', 'engagement.survey.*', 'complaints.public.*', 'complaints.follow-up.*', 'portal.*', 'attendance.device.*', 'workers.*'];

    private const array KEPT_HEADERS = ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'];

    public function __construct(private HandleInertiaRequests $inertia) {}

    public function respond(Response $response, Request $request, Throwable $exception): Response
    {
        $status = $response->getStatusCode();

        // A response someone built on purpose (a limiter's own words) is kept as it is.
        if ($exception instanceof HttpResponseException
            || ! in_array($status, self::SHOWN, true)
            || $request->expectsJson()
            || ($status >= 500 && config('app.debug') === true)) {
            return $response;
        }

        // The id in X-Request-Id and in every log line of this request: what a person quotes to support when the server
        // failed. Only then: a value that changes on every request would tell apart two pages that must be the same
        // (an unknown link and a revoked one both answer the same 404).
        $requestId = Context::get('request_id');
        $props = ['status' => $status, 'survey' => $request->routeIs(...self::WORKER_ROUTES), 'requestId' => $status >= 500 && is_string($requestId) ? $requestId : null];

        try {
            // An address no route answers never ran the web middleware: share here what every page gets.
            if (Inertia::getShared('auth') === null) {
                Inertia::share($this->inertia->share($request));
            }

            $page = Inertia::render('error', $props)->toResponse($request);
        } catch (Throwable) {
            // What every page shares reads the database: when that is what failed, the page still shows, with nothing of it.
            Inertia::flushShared();
            Inertia::share($this->inertia->safeShare($request));
            $page = Inertia::render('error', $props)->toResponse($request);
        }

        $page->setStatusCode($status);

        foreach (self::KEPT_HEADERS as $header) {
            if ($response->headers->has($header)) {
                $page->headers->set($header, $response->headers->get($header));
            }
        }

        // The router prepares what a route returns; nothing prepares what the exception handler answers.
        return $page->prepare($request);
    }
}
