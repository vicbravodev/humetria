<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Providers;

use App\Models\User;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only the superadmin manages companies, through Gate::before; everyone else gets a 404 that reveals nothing.
        Gate::define('manage-tenants', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());
    }
}
