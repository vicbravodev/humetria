<?php

declare(strict_types=1);

// Required from routes/web.php: the routes of the time-clock devices, outside the company group.
// Every name starts with attendance.device.

use App\Modules\Attendance\Http\Controllers\EnrollmentController;
use App\Modules\Attendance\Http\Controllers\KioskController;
use App\Modules\Attendance\Http\Middleware\ResolveDeviceTenant;
use Illuminate\Support\Facades\Route;

// No account: the device's own credential authorizes it, inside the company of the slug (ResolveDeviceTenant), which an
// unknown or suspended company answers with a 404. Throttled per address before anything is read.
Route::middleware(['throttle:attendance-device', ResolveDeviceTenant::class])->group(function (): void {
    // The QR screen of a center: its link (shown once to RH) opens once, swapping its token for a secret kept only in an
    // httpOnly cookie; the link dies.
    Route::get('a/{tenant:slug}/kiosk/{token}', [KioskController::class, 'activate'])->name('attendance.device.kiosk.activate');
    Route::get('a/{tenant:slug}/kiosk', [KioskController::class, 'show'])->name('attendance.device.kiosk.show');
    Route::get('a/{tenant:slug}/qr', [KioskController::class, 'qr'])->name('attendance.device.kiosk.qr');
});

// A person links their device with the link RH sent them: good once and for 48 hours; the link is the credential. Its own
// limiter, per address and per link.
Route::middleware(['throttle:attendance-enroll', ResolveDeviceTenant::class])->group(function (): void {
    Route::get('a/{tenant:slug}/enroll/{token}', [EnrollmentController::class, 'show'])->name('attendance.device.enroll.show');
    Route::post('a/{tenant:slug}/enroll/{token}/options', [EnrollmentController::class, 'options'])->name('attendance.device.enroll.options');
    Route::post('a/{tenant:slug}/enroll/{token}/register', [EnrollmentController::class, 'register'])->name('attendance.device.enroll.register');
});
