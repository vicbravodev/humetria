<?php

declare(strict_types=1);

namespace App\Modules\Demos\Providers;

use App\Models\User;
use App\Modules\Demos\Contracts\DemoLeads;
use App\Modules\Demos\Contracts\DemoShowcase;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Demos\Contracts\DoorControl;
use App\Modules\Demos\Support\ClosedDoor;
use App\Modules\Demos\Support\DemoLeadRegistry;
use Database\Seeders\Demo\DemoShowcaseLinks;
use Database\Seeders\Demo\DemoWorkspaceRunner;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class DemosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DemoLeads::class, DemoLeadRegistry::class);
        // The story lives outside the modules (database/seeders/Demo, autoloaded in production too): it drives every module's actions.
        $this->app->bind(DemoWorkspace::class, DemoWorkspaceRunner::class);
        $this->app->bind(DemoShowcase::class, DemoShowcaseLinks::class);
        // Until the door is merged; then its actions take this binding (one line).
        $this->app->bind(DoorControl::class, ClosedDoor::class);
    }

    public function boot(): void
    {
        // Only the superadmin reads demo requests, through Gate::before; everyone else gets a 404 that reveals nothing.
        // Read only: support sees the requests too; the superadmin passes through Gate::before.
        Gate::define('view-demo-requests', fn (User $user): AccessResponse => $user->isSupport() ? AccessResponse::allow() : AccessResponse::denyAsNotFound());

        // Working a request (status, note, creating its company) is the superadmin's alone, through Gate::before.
        Gate::define('manage-demo-requests', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());

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
