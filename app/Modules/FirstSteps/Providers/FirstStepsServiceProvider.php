<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Providers;

use App\Modules\FirstSteps\Contracts\FirstSteps;
use App\Modules\FirstSteps\Models\FirstStepsState;
use App\Modules\FirstSteps\Policies\FirstStepsStatePolicy;
use App\Modules\FirstSteps\Support\CompanyFirstSteps;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Primeros pasos, the guided tour of a company's owner.
 */
final class FirstStepsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FirstSteps::class, CompanyFirstSteps::class);
    }

    public function boot(): void
    {
        Gate::policy(FirstStepsState::class, FirstStepsStatePolicy::class);
    }
}
