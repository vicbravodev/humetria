<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registro de jornada. Its panel routes live in routes/attendance.php, required from the company group in
 * routes/company.php, and the device routes in routes/attendance-device.php, required from routes/web.php. Contracts and
 * listeners are bound here as they appear.
 */
final class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
