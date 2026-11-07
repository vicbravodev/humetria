<?php

declare(strict_types=1);

// Required from routes/web.php: the routes of the time-clock devices, outside the company group.
// Every name starts with attendance.device.

use App\Modules\Attendance\Http\Controllers\EnrollmentController;
use App\Modules\Attendance\Http\Controllers\KioskController;
use App\Modules\Attendance\Http\Controllers\MarkController;
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

// A person marks from their linked device (no account): the key it signs with names the person, inside the company of the
// slug. The page opens from the address RH gave (`a/{slug}`) or from the QR of the center's screen (`a/{slug}/punch?q=`).
// Its own limiter: generous per address (a whole shift behind one router) and strict per key.
Route::middleware(['throttle:attendance-punch', ResolveDeviceTenant::class])->group(function (): void {
    Route::get('a/{tenant:slug}', [MarkController::class, 'show'])->name('attendance.device.punch.home');
    Route::get('a/{tenant:slug}/punch', [MarkController::class, 'show'])->name('attendance.device.punch.show');
    Route::post('a/{tenant:slug}/punch/options', [MarkController::class, 'options'])->name('attendance.device.punch.options');
    Route::post('a/{tenant:slug}/punch', [MarkController::class, 'store'])->name('attendance.device.punch.store');
    Route::post('a/{tenant:slug}/punch/forget', [MarkController::class, 'forget'])->name('attendance.device.punch.forget');
});
