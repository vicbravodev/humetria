<?php

declare(strict_types=1);

namespace App\Modules\Operations\Providers;

use App\Models\User;
use App\Modules\Operations\Contracts\SchedulerHeartbeat;
use App\Modules\Operations\Support\CachedHeartbeat;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Operations: how the platform itself is running, for its superadmin. It owns no table. */
final class OperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SchedulerHeartbeat::class, CachedHeartbeat::class);
    }

    public function boot(): void
    {
        // The superadmin's alone, through Gate::before: support does not see the platform's insides.
        Gate::define('view-operations', fn (User $user): AccessResponse => AccessResponse::deny());
    }
}
