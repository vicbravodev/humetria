<?php

declare(strict_types=1);

namespace App\Modules\Seating\Providers;

use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Seating\Listeners\EndOnDeactivationOrMove;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Ley Silla. Its panel routes live in routes/seating.php, required from the company group in routes/company.php. The
 * listeners and contracts of the module are bound here as they appear.
 */
final class SeatingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen([EmployeeDeactivated::class, EmployeeMoved::class], EndOnDeactivationOrMove::class);
    }
}
