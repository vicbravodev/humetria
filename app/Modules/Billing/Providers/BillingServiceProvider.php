<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Models\User;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // The price of the plan is asked again as the form is typed: a person has a minute's worth of those, no more.
        RateLimiter::for('billing-preview', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->user()?->id));
    }
}
