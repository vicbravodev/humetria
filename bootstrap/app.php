<?php

declare(strict_types=1);

use App\Http\ErrorPage;
use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EndRevokedImpersonation;
use App\Http\Middleware\ForbidWhileImpersonating;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // First of the global stack: every response gets the headers, even the ones the router answers itself.
        $middleware->prepend(SecurityHeaders::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            // The id first, so even a revoked request's log line has one.
            AssignRequestId::class,
            // Before the user's context: a revoked impersonation must not be tagged as the owner's request.
            EndRevokedImpersonation::class,
            AssignRequestContext::class,
            ForbidWhileImpersonating::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Survey answers never go back into the session, even from a submission that fails validation (criterio C-22).
        $exceptions->dontFlash(['answers']);

        // A failed query's message embeds its bindings (emails, phones, answers…). Report it without them.
        $exceptions->report(function (QueryException $exception): false {
            Log::error('database.query_failed', [
                'sql' => $exception->getSql(),
                'code' => (string) $exception->getCode(),
                'connection' => $exception->getConnectionName(),
            ]);

            return false;
        });

        // A mail server's refusal usually quotes the recipient's address. Report it by class and code only.
        $exceptions->report(function (TransportExceptionInterface $exception): false {
            Log::error('mail.send_failed', [
                'exception' => class_basename($exception),
                'code' => (string) $exception->getCode(),
            ]);

            return false;
        });

        // Inertia would show the bare 429 of a submit in a modal: send it back with a toast. A visit (GET) gets
        // errors/429 instead: sent back, a page over its own limit would redirect to itself forever.
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request): ?RedirectResponse {
            if (! $request->hasHeader(Header::INERTIA) || $request->isMethodSafe()) {
                return null;
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.']);

            return back();
        });

        $exceptions->respond(fn (Response $response, Throwable $exception, Request $request): Response => app(ErrorPage::class)->respond($response, $request));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
