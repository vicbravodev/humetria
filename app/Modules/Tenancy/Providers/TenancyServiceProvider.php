<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Providers;

use App\Models\User;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only the superadmin manages companies, through Gate::before; everyone else gets a 404 that reveals nothing.
        Gate::define('manage-tenants', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());

        // The invitation link is public. Viewing it has its own budget, so the page shown again after a mistyped
        // password does not spend the attempts.
        RateLimiter::for('invitation-views', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        // Each link gets ten attempts a minute from an address, and the address sixty across links. The path carries
        // the token; the throttle stores only a hash of the key.
        RateLimiter::for('invitation-attempts', fn (Request $request): array => [
            Limit::perMinute(10)->by('link:'.$request->path().'|'.$request->ip()),
            Limit::perMinute(60)->by('address:'.$request->ip()),
        ]);
    }
}
