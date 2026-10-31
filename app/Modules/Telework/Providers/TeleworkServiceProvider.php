<?php

declare(strict_types=1);

namespace App\Modules\Telework\Providers;

use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Telework\Events\DocumentPublished;
use App\Modules\Telework\Listeners\EndOnDeactivation;
use App\Modules\Telework\Listeners\EndOnMove;
use App\Modules\Telework\Listeners\RequestOnPublication;
use App\Modules\Telework\Listeners\RequestPolicyFromNewStaff;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * NOM-037 (teletrabajo). Its panel routes live in routes/telework.php, required from the company group in
 * routes/company.php; the public portal is routes/portal.php. The contracts and listeners of the module are bound here as they appear.
 */
final class TeleworkServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // As the survey: per link, so a room on one Wi-Fi can use theirs at once, and a generous cap per address against
        // bulk guessing. The link is hashed: a key never holds it.
        $link = function (Request $request): string {
            $token = $request->route('token');

            return 'portal:'.hash('sha256', is_string($token) ? $token : '');
        };
        RateLimiter::for('portal-views', fn (Request $request): array => [
            Limit::perMinute(30)->by($link($request)),
            Limit::perMinute(600)->by('portal-address:'.$request->ip()),
        ]);
        RateLimiter::for('portal-actions', fn (Request $request): array => [
            Limit::perMinute(10)->by($link($request)),
            Limit::perMinute(300)->by('portal-actions-address:'.$request->ip()),
        ]);

        Event::listen(EmployeeDeactivated::class, EndOnDeactivation::class);
        Event::listen(EmployeeMoved::class, EndOnMove::class);
        Event::listen(DocumentPublished::class, RequestOnPublication::class);
        Event::listen([EmployeeCreated::class, EmployeeReactivated::class, EmployeeMoved::class], RequestPolicyFromNewStaff::class);
    }
}
