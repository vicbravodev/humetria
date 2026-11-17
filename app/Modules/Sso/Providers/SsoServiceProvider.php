<?php

declare(strict_types=1);

namespace App\Modules\Sso\Providers;

use App\Modules\Sso\Contracts\DomainOwnership;
use App\Modules\Sso\Support\DnsDomainOwnership;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Single sign-on (OpenID Connect) for the people of a company who have an account.
 */
final class SsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DomainOwnership::class, DnsDomainOwnership::class);
    }

    public function boot(): void
    {
        // Starting, finishing and looking up a sign-in: each costs a call to somebody else's server, so per address.
        RateLimiter::for('sso', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
    }
}
