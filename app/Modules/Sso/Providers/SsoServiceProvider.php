<?php

declare(strict_types=1);

namespace App\Modules\Sso\Providers;

use App\Modules\Sso\Contracts\DomainOwnership;
use App\Modules\Sso\Support\DnsDomainOwnership;
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
}
