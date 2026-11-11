<?php

declare(strict_types=1);

namespace App\Modules\Organization\Providers;

use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Contracts\CompensationRoster;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Organization\Contracts\JobGrades;
use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Contracts\Seniority;
use App\Modules\Organization\Support\CompanyAreas;
use App\Modules\Organization\Support\CompanyCompensationRoster;
use App\Modules\Organization\Support\CompanyHeadcount;
use App\Modules\Organization\Support\CompanyJobGrades;
use App\Modules\Organization\Support\CompanyReportingLines;
use App\Modules\Organization\Support\CompanyRoster;
use App\Modules\Organization\Support\CompanySeniority;
use Illuminate\Support\ServiceProvider;

final class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Areas::class, CompanyAreas::class);
        $this->app->bind(CompensationRoster::class, CompanyCompensationRoster::class);
        $this->app->bind(Headcount::class, CompanyHeadcount::class);
        $this->app->bind(JobGrades::class, CompanyJobGrades::class);
        $this->app->bind(ReportingLines::class, CompanyReportingLines::class);
        $this->app->bind(Roster::class, CompanyRoster::class);
        $this->app->bind(Seniority::class, CompanySeniority::class);
    }
}
