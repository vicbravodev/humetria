<?php

declare(strict_types=1);

namespace App\Modules\Demos\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class DemosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The demo form is public: cap each address so nobody can flood the table.
        RateLimiter::for('demo-requests', function (Request $request): array {
            $tooMany = fn (Request $request, array $headers): Response => new Response(
                'Recibimos demasiadas solicitudes desde tu conexión. Espera un momento e inténtalo de nuevo.',
                Response::HTTP_TOO_MANY_REQUESTS,
                $headers,
            );

            return [
                Limit::perMinute(5)->by('minute:'.$request->ip())->response($tooMany),
                Limit::perDay(20)->by('day:'.$request->ip())->response($tooMany),
            ];
        });
    }
}
