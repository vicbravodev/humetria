<?php

declare(strict_types=1);

namespace App\Modules\Organization\Providers;

use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Support\CompanyAreas;
use App\Modules\Organization\Support\CompanyHeadcount;
use App\Modules\Organization\Support\CompanyRoster;
use Illuminate\Support\ServiceProvider;

final class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Areas::class, CompanyAreas::class);
        $this->app->bind(Headcount::class, CompanyHeadcount::class);
        $this->app->bind(Roster::class, CompanyRoster::class);
    }
}
