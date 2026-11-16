<?php

declare(strict_types=1);

namespace App\Modules\Demos\Providers;

use App\Models\User;
use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Contracts\DemoEntrance;
use App\Modules\Demos\Contracts\DemoLeads;
use App\Modules\Demos\Contracts\DemoShowcase;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Demos\Contracts\DoorControl;
use App\Modules\Demos\Support\DemoLeadRegistry;
use App\Modules\Demos\Support\DoorKeeper;
use Database\Seeders\Demo\DemoShowcaseLinks;
use Database\Seeders\Demo\DemoWorkspaceRunner;
use Database\Seeders\Demo\RosterDoorPool;
use Database\Seeders\Demo\WorkerAccessEntrance;
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
        $this->app->bind(DemoDoorPool::class, RosterDoorPool::class);
        $this->app->bind(DemoEntrance::class, WorkerAccessEntrance::class);
        $this->app->bind(DemoShowcase::class, DemoShowcaseLinks::class);
        $this->app->bind(DoorControl::class, DoorKeeper::class);
    }

    public function boot(): void
    {
        // Only the superadmin reads demo requests, through Gate::before; everyone else gets a 404 that reveals nothing.
        // Read only: support sees the requests too; the superadmin passes through Gate::before.
        Gate::define('view-demo-requests', fn (User $user): AccessResponse => $user->isSupport() ? AccessResponse::allow() : AccessResponse::denyAsNotFound());

        // Working a request (status, note, creating its company) is the superadmin's alone, through Gate::before.
        Gate::define('manage-demo-requests', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());

        // The demo room (preparing, resetting and sharing the demo company) is the superadmin's alone, through Gate::before;
        // everyone else, support included, gets a 404 that reveals nothing.
        Gate::define('manage-demo', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());

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

        // The door of a live demo: a whole room scans it from one Wi-Fi within a minute, and nobody can try codes in bulk.
        RateLimiter::for('demo-door', fn (Request $request): Limit => Limit::perMinute(120)->by('demo-door:'.$request->ip()));
    }
}
