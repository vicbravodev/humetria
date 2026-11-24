<?php

declare(strict_types=1);

// Required from routes/web.php: the routes of the time-clock devices, outside the company group: the QR screen of a center
// under a/{slug}, and what a person does (Marcar, Mi semana, a correction) under me/{slug}, next to the rest of their pages.
// Every name starts with attendance.device.

use App\Modules\Attendance\Http\Controllers\AgreementController;
use App\Modules\Attendance\Http\Controllers\CorrectionRequestController;
use App\Modules\Attendance\Http\Controllers\KioskController;
use App\Modules\Attendance\Http\Controllers\MarkController;
use App\Modules\Attendance\Http\Controllers\WeekController;
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

// A person marks with their key (no account): the key it signs with names the person, inside the company of the
// slug. The page opens from the person's start (`me/{slug}`) or from the QR of the center's screen (`me/{slug}/punch?q=`).
// Its own limiter: generous per address (a whole shift behind one router) and strict per key.
Route::middleware(['throttle:attendance-punch', ResolveDeviceTenant::class])->group(function (): void {
    Route::get('me/{tenant:slug}/punch', [MarkController::class, 'show'])->name('attendance.device.punch.show');
    Route::post('me/{tenant:slug}/punch/options', [MarkController::class, 'options'])->name('attendance.device.punch.options');
    Route::post('me/{tenant:slug}/punch', [MarkController::class, 'store'])->name('attendance.device.punch.store');
    Route::post('me/{tenant:slug}/punch/forget', [MarkController::class, 'forget'])->name('attendance.device.punch.forget');
    // The agreement, accepted from Marcar before the first mark (and again when its text changes): a signature of its own.
    Route::post('me/{tenant:slug}/agreement/options', [AgreementController::class, 'options'])->name('attendance.device.agreement.options');
    Route::post('me/{tenant:slug}/agreement', [AgreementController::class, 'accept'])->name('attendance.device.agreement.accept');
});

// «Mi semana»: a person sees one of their weeks and confirms or disputes it (no account). Each read and each answer takes a
// signature of its own; the key alone names the person. Its own limiter, generous per address and per key.
Route::middleware(['throttle:attendance-week', ResolveDeviceTenant::class])->group(function (): void {
    Route::get('me/{tenant:slug}/week/{start?}', [WeekController::class, 'show'])->where('start', '\d{4}-\d{2}-\d{2}')->name('attendance.device.week.show');
    Route::post('me/{tenant:slug}/week/options', [WeekController::class, 'options'])->name('attendance.device.week.options');
    Route::post('me/{tenant:slug}/week', [WeekController::class, 'read'])->name('attendance.device.week.read');
    Route::post('me/{tenant:slug}/week/{start}/confirm', [WeekController::class, 'confirm'])->where('start', '\d{4}-\d{2}-\d{2}')->name('attendance.device.week.confirm');
    Route::post('me/{tenant:slug}/week/{start}/dispute', [WeekController::class, 'dispute'])->where('start', '\d{4}-\d{2}-\d{2}')->name('attendance.device.week.dispute');
});

// A person asks RH to correct their record (a mark that is wrong, one they forgot), from «Mi semana» or from Marcar. Each
// request takes a signature of its own; the key alone names the person. Its own limiter, generous per address and per key.
Route::middleware(['throttle:attendance-correction', ResolveDeviceTenant::class])->group(function (): void {
    Route::post('me/{tenant:slug}/correction/options', [CorrectionRequestController::class, 'options'])->name('attendance.device.correction.options');
    Route::post('me/{tenant:slug}/correction', [CorrectionRequestController::class, 'store'])->name('attendance.device.correction.store');
});
