<?php

declare(strict_types=1);

namespace App\Modules\Seating\Providers;

use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Seating\Contracts\SeatingRoute;
use App\Modules\Seating\Events\PersonInputSubmitted;
use App\Modules\Seating\Events\RiskAssessmentSigned;
use App\Modules\Seating\Listeners\EndOnDeactivationOrMove;
use App\Modules\Seating\Listeners\FlagOnPersonInput;
use App\Modules\Seating\Listeners\RequestOnSignature;
use App\Modules\Seating\Support\CompanySeatingRoute;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Ley Silla. Its panel routes live in routes/seating.php, required from the company group in routes/company.php. The
 * listeners and contracts of the module are bound here as they appear.
 */
final class SeatingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SeatingRoute::class, CompanySeatingRoute::class);
    }

    public function boot(): void
    {
        Event::listen([EmployeeDeactivated::class, EmployeeMoved::class], EndOnDeactivationOrMove::class);
        Event::listen(PersonInputSubmitted::class, FlagOnPersonInput::class);
        Event::listen(RiskAssessmentSigned::class, RequestOnSignature::class);
    }
}
