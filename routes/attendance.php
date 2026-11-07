<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/attendance.
// Every name starts with tenant.attendance.

use App\Modules\Attendance\Http\Controllers\CorrectionController;
use App\Modules\Attendance\Http\Controllers\KioskController;
use Illuminate\Support\Facades\Route;

/** @var string $notReserved */
// The QR screens of a center: issued with a link shown once, revoked (the row stays, with when).
Route::post('admin/{tenant:slug}/attendance/kiosks', [KioskController::class, 'store'])->where('tenant', $notReserved)->name('tenant.attendance.kiosks.store');
Route::delete('admin/{tenant:slug}/attendance/kiosks/{kiosk}', [KioskController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid('kiosk')->name('tenant.attendance.kiosks.destroy');

// The people's requests to correct their record: owner, hr and viewer read the queue; owner and hr resolve each, once.
Route::get('admin/{tenant:slug}/attendance/corrections', [CorrectionController::class, 'index'])->where('tenant', $notReserved)->name('tenant.attendance.corrections.index');
Route::post('admin/{tenant:slug}/attendance/corrections/{correction}/approve', [CorrectionController::class, 'approve'])->where('tenant', $notReserved)->whereUuid('correction')->name('tenant.attendance.corrections.approve');
Route::post('admin/{tenant:slug}/attendance/corrections/{correction}/reject', [CorrectionController::class, 'reject'])->where('tenant', $notReserved)->whereUuid('correction')->name('tenant.attendance.corrections.reject');
