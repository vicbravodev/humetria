<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Providers;

use App\Modules\Attendance\Events\MarkCorrected;
use App\Modules\Attendance\Listeners\ReopenOnCorrection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registro de jornada. Its panel routes live in routes/attendance.php, required from the company group in
 * routes/company.php, and the device routes in routes/attendance-device.php, required from routes/web.php. Contracts and
 * listeners are bound here as they appear.
 */
final class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(MarkCorrected::class, ReopenOnCorrection::class);

        // A screen asks for a code about twice a minute: per address, so a center with several screens behind one router fits,
        // and guessing a token is slowed down before any is read.
        RateLimiter::for('attendance-device', fn (Request $request): Limit => Limit::perMinute(120)->by('attendance-device:'.$request->ip()));

        // Linking a device takes three requests (the page, the options, the key): per address, and per link so that guessing
        // or replaying one is slowed down even from many addresses. Only the link's hash names its counter.
        RateLimiter::for('attendance-enroll', fn (Request $request): array => [
            Limit::perMinute(30)->by('attendance-enroll-ip:'.$request->ip()),
            Limit::perMinute(10)->by('attendance-enroll-link:'.hash('sha256', $request->route('token') ?? '')),
        ]);

        // Marking takes three requests (the page, the options, the signature). Per address 300 a minute: a shift of a hundred
        // people behind one router marking in the same minute fits. Per key 12 a minute: a person marks a few times a day,
        // so a key hammering the endpoint is stopped whatever the address. Only the key id's hash names its counter.
        RateLimiter::for('attendance-punch', function (Request $request): array {
            $key = $request->input('credential.id');

            return [
                Limit::perMinute(300)->by('attendance-punch-ip:'.$request->ip()),
                ...(is_string($key) ? [Limit::perMinute(12)->by('attendance-punch-key:'.hash('sha256', $key))] : []),
            ];
        });
    }
}
