<?php

declare(strict_types=1);

namespace App\Modules\Leave\Providers;

use App\Modules\Leave\Contracts\Absences;
use App\Modules\Leave\Listeners\CloseOnDeactivation;
use App\Modules\Leave\Listeners\VoidTerminationOnReactivation;
use App\Modules\Leave\Support\CompanyAbsences;
use App\Modules\Leave\Support\VacationsPortalSection;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeReactivated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Vacaciones. Its panel routes live in routes/leave.php, required from the company group in routes/company.php, and the
 * portal routes in routes/leave-portal.php, required from routes/web.php. Contracts and listeners are bound here as they
 * appear.
 */
final class LeaveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([VacationsPortalSection::class], 'portal.sections');
        $this->app->bind(Absences::class, CompanyAbsences::class);
    }

    public function boot(): void
    {
        Event::listen(EmployeeDeactivated::class, CloseOnDeactivation::class);
        Event::listen(EmployeeReactivated::class, VoidTerminationOnReactivation::class);
    }
}
