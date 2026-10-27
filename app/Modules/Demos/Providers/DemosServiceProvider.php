<?php

declare(strict_types=1);

namespace App\Modules\Demos\Providers;

use App\Models\User;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class DemosServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only the superadmin reads demo requests, through Gate::before; everyone else gets a 404 that reveals nothing.
        Gate::define('view-demo-requests', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());

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
