<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

/**
 * The queue dashboard at /horizon is for the superadmin only, in every environment (Horizon's stock provider opens it
 * to anyone in local). Its routes also carry `auth` (config/horizon.php), so a guest goes to the login page.
 */
final class HorizonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('viewHorizon', fn (User $user): bool => $user->hasRole(User::SUPERADMIN));

        Horizon::auth(fn (): bool => Gate::allows('viewHorizon'));

        // The dashboard inlines its CSS and JS: they carry the request's CSP nonce instead of loosening the policy.
        View::composer('horizon::layout', fn () => Horizon::cspNonce(Vite::cspNonce() ?? Vite::useCspNonce()));
    }
}
