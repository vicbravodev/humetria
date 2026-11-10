<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Models\User;
use App\Modules\Billing\Contracts\PlanSteps;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Listeners\CountSentMail;
use App\Modules\Billing\Support\LedgerUsageMeter;
use App\Modules\Billing\Support\SubscriptionSteps;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PlanSteps::class, SubscriptionSteps::class);
        $this->app->bind(UsageMeter::class, LedgerUsageMeter::class);
    }

    public function boot(): void
    {
        Event::listen(NotificationSent::class, CountSentMail::class);

        // Looking at who pays what is for the support role too (the superadmin passes through Gate::before); it changes
        // nothing. Everyone else gets a 404 that reveals nothing.
        Gate::define('view-billing', fn (User $user): AccessResponse => $user->isSupport() ? AccessResponse::allow() : AccessResponse::denyAsNotFound());

        // Accounts, links, plans, prices and charges are the superadmin's alone, through Gate::before.
        Gate::define('manage-billing', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());

        // The price of the plan is asked again as the form is typed: a person has a minute's worth of those, no more.
        RateLimiter::for('billing-preview', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->user()?->id));
    }
}
