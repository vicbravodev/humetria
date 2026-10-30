<?php

declare(strict_types=1);

namespace App\Modules\Organization\Providers;

use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Organization\Support\CompanyHeadcount;
use Illuminate\Support\ServiceProvider;

final class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Headcount::class, CompanyHeadcount::class);
    }
}
