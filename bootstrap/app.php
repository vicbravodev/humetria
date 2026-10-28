<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            AssignRequestContext::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
