<?php

declare(strict_types=1);

namespace App\Modules\Telework\Providers;

use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Telework\Contracts\Committee;
use App\Modules\Telework\Contracts\TeleworkApplicability;
use App\Modules\Telework\Contracts\TeleworkRoute;
use App\Modules\Telework\Contracts\TeleworkStanding;
use App\Modules\Telework\Events\DocumentPublished;
use App\Modules\Telework\Listeners\EndOnDeactivation;
use App\Modules\Telework\Listeners\EndOnMove;
use App\Modules\Telework\Listeners\RequestOnPublication;
use App\Modules\Telework\Listeners\RequestPolicyFromNewStaff;
use App\Modules\Telework\Support\CommitteeGate;
use App\Modules\Telework\Support\CompanyTeleworkApplicability;
use App\Modules\Telework\Support\CompanyTeleworkRoute;
use App\Modules\Telework\Support\CompanyTeleworkStanding;
use App\Modules\Telework\Support\TeleworkWorkerSection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * NOM-037 (teletrabajo). Its panel routes live in routes/telework.php, required from the company group in
 * routes/company.php; the person's telework pages are routes/telework-portal.php. The contracts and listeners of the module are bound here as they appear.
 */
final class TeleworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Committee::class, CommitteeGate::class);
        $this->app->bind(TeleworkStanding::class, CompanyTeleworkStanding::class);
        $this->app->bind(TeleworkRoute::class, CompanyTeleworkRoute::class);
        $this->app->bind(TeleworkApplicability::class, CompanyTeleworkApplicability::class);
        $this->app->tag([TeleworkWorkerSection::class], 'worker.sections');
    }

    public function boot(): void
    {
        Event::listen(EmployeeDeactivated::class, EndOnDeactivation::class);
        Event::listen(EmployeeMoved::class, EndOnMove::class);
        Event::listen(DocumentPublished::class, RequestOnPublication::class);
        Event::listen([EmployeeCreated::class, EmployeeReactivated::class, EmployeeMoved::class], RequestPolicyFromNewStaff::class);
    }
}
