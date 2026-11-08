<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/attendance.
// Every name starts with tenant.attendance.

use App\Modules\Attendance\Http\Controllers\AttendanceController;
use App\Modules\Attendance\Http\Controllers\CorrectionController;
use App\Modules\Attendance\Http\Controllers\DeviceController;
use App\Modules\Attendance\Http\Controllers\KioskController;
use App\Modules\Attendance\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

/** @var string $notReserved */
// RH's overview: one week of the people by state, and the centers.
Route::get('admin/{tenant:slug}/attendance', [AttendanceController::class, 'index'])->where('tenant', $notReserved)->name('tenant.attendance.index');

// A center of the registro: its zones (where its people may mark) and its QR screens. Zones are added and changed, never
// deleted: the marks taken in one point at it.
Route::get('admin/{tenant:slug}/attendance/centers/{workCenter}', [AttendanceController::class, 'center'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.attendance.center');
Route::post('admin/{tenant:slug}/attendance/centers/{workCenter}/zones', [ZoneController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.attendance.zones.store');
Route::put('admin/{tenant:slug}/attendance/centers/{workCenter}/zones/{zone}', [ZoneController::class, 'update'])->where('tenant', $notReserved)->whereUuid(['workCenter', 'zone'])->name('tenant.attendance.zones.update');

// The QR screens of a center: issued with a link shown once, revoked (the row stays, with when).
Route::post('admin/{tenant:slug}/attendance/kiosks', [KioskController::class, 'store'])->where('tenant', $notReserved)->name('tenant.attendance.kiosks.store');
Route::post('admin/{tenant:slug}/attendance/kiosks/{kiosk}/reissue', [KioskController::class, 'reissue'])->where('tenant', $notReserved)->whereUuid('kiosk')->name('tenant.attendance.kiosks.reissue');
Route::delete('admin/{tenant:slug}/attendance/kiosks/{kiosk}', [KioskController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid('kiosk')->name('tenant.attendance.kiosks.destroy');

// The people's requests to correct their record: owner, hr and viewer read the queue; owner and hr resolve each, once.
Route::get('admin/{tenant:slug}/attendance/corrections', [CorrectionController::class, 'index'])->where('tenant', $notReserved)->name('tenant.attendance.corrections.index');
Route::post('admin/{tenant:slug}/attendance/corrections/{correction}/approve', [CorrectionController::class, 'approve'])->where('tenant', $notReserved)->whereUuid('correction')->name('tenant.attendance.corrections.approve');
Route::post('admin/{tenant:slug}/attendance/corrections/{correction}/reject', [CorrectionController::class, 'reject'])->where('tenant', $notReserved)->whereUuid('correction')->name('tenant.attendance.corrections.reject');

// The people's linked devices: RH sends the link to link one (emailed; shown to RH only for a person without email) and revokes
// one. The keys themselves never reach the panel.
Route::get('admin/{tenant:slug}/attendance/devices', [DeviceController::class, 'index'])->where('tenant', $notReserved)->name('tenant.attendance.devices.index');
Route::post('admin/{tenant:slug}/attendance/devices/enroll', [DeviceController::class, 'enroll'])->where('tenant', $notReserved)->name('tenant.attendance.devices.enroll');
// Every person of a center without a device gets their link by email, one queued job each.
Route::post('admin/{tenant:slug}/attendance/devices/enroll-all', [DeviceController::class, 'enrollAll'])->where('tenant', $notReserved)->name('tenant.attendance.devices.enroll-all');
Route::post('admin/{tenant:slug}/attendance/devices/{device}/revoke', [DeviceController::class, 'revoke'])->where('tenant', $notReserved)->whereUuid('device')->name('tenant.attendance.devices.revoke');
