<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/attendance.
// Every name starts with tenant.attendance.

use App\Modules\Attendance\Http\Controllers\KioskController;
use Illuminate\Support\Facades\Route;

/** @var string $notReserved */
// The QR screens of a center: issued with a link shown once, revoked (the row stays, with when).
Route::post('admin/{tenant:slug}/attendance/kiosks', [KioskController::class, 'store'])->where('tenant', $notReserved)->name('tenant.attendance.kiosks.store');
Route::delete('admin/{tenant:slug}/attendance/kiosks/{kiosk}', [KioskController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid('kiosk')->name('tenant.attendance.kiosks.destroy');
