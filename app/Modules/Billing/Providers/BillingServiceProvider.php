<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Models\User;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Looking at who pays what is for the support role too (the superadmin passes through Gate::before); it changes
        // nothing. Everyone else gets a 404 that reveals nothing.
        Gate::define('view-billing', fn (User $user): AccessResponse => $user->isSupport() ? AccessResponse::allow() : AccessResponse::denyAsNotFound());

        // Accounts, links, plans, prices and charges are the superadmin's alone, through Gate::before.
        Gate::define('manage-billing', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());
    }
}
