<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/attendance.
// Every name starts with tenant.attendance.

use App\Modules\Attendance\Http\Controllers\AttendanceController;
use App\Modules\Attendance\Http\Controllers\CenterModeController;
use App\Modules\Attendance\Http\Controllers\CorrectionController;
use App\Modules\Attendance\Http\Controllers\KioskController;
use App\Modules\Attendance\Http\Controllers\MarkCorrectionController;
use App\Modules\Attendance\Http\Controllers\OfflineMarkController;
use App\Modules\Attendance\Http\Controllers\PlaceController;
use App\Modules\Attendance\Http\Controllers\PolicyController;
use App\Modules\Attendance\Http\Controllers\ReportController;
use App\Modules\Attendance\Http\Controllers\ScheduleController;
use App\Modules\Attendance\Http\Controllers\WeekResolutionController;
use App\Modules\Attendance\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

/** @var string $notReserved */
// RH's overview: one week of the people by state, and the centers.
Route::get('admin/{tenant:slug}/attendance', [AttendanceController::class, 'index'])->where('tenant', $notReserved)->name('tenant.attendance.index');

// A person's disputed week: owner and hr resolve it with a note, once, and it goes back to the person (criterio 5.2).
Route::post('admin/{tenant:slug}/attendance/weeks/{week}/resolve', WeekResolutionController::class)->where('tenant', $notReserved)->whereUuid('week')->name('tenant.attendance.weeks.resolve');

// The road to the first mark: what each center, schedule, phone and the policy still need before 1 January 2027.
Route::get('admin/{tenant:slug}/attendance/setup', [AttendanceController::class, 'setup'])->where('tenant', $notReserved)->name('tenant.attendance.setup');

// A center of the registro: its zones (where its people may mark) and its QR screens. Zones are added and changed, never
// deleted: the marks taken in one point at it.
Route::get('admin/{tenant:slug}/attendance/centers/{workCenter}', [AttendanceController::class, 'center'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.attendance.center');
Route::post('admin/{tenant:slug}/attendance/centers/{workCenter}/zones', [ZoneController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.attendance.zones.store');
Route::put('admin/{tenant:slug}/attendance/centers/{workCenter}/zones/{zone}', [ZoneController::class, 'update'])->where('tenant', $notReserved)->whereUuid(['workCenter', 'zone'])->name('tenant.attendance.zones.update');
// How a center's people mark: as the company's policy says, or in the field (no zone nor screen needed).
Route::put('admin/{tenant:slug}/attendance/centers/{workCenter}/mode', CenterModeController::class)->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.attendance.centers.mode');
// The places that match an address, to drop a zone there from the map; a few a minute, as Nominatim asks.
Route::get('admin/{tenant:slug}/attendance/places', PlaceController::class)->where('tenant', $notReserved)->middleware('throttle:20,1')->name('tenant.attendance.places');

// The QR screens of a center: issued with a link shown once, revoked (the row stays, with when).
Route::post('admin/{tenant:slug}/attendance/kiosks', [KioskController::class, 'store'])->where('tenant', $notReserved)->name('tenant.attendance.kiosks.store');
Route::post('admin/{tenant:slug}/attendance/kiosks/{kiosk}/reissue', [KioskController::class, 'reissue'])->where('tenant', $notReserved)->whereUuid('kiosk')->name('tenant.attendance.kiosks.reissue');
Route::delete('admin/{tenant:slug}/attendance/kiosks/{kiosk}', [KioskController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid('kiosk')->name('tenant.attendance.kiosks.destroy');

// The people's requests to correct their record: owner, hr and viewer read the queue; owner and hr resolve each, once.
Route::get('admin/{tenant:slug}/attendance/corrections', [CorrectionController::class, 'index'])->where('tenant', $notReserved)->name('tenant.attendance.corrections.index');
Route::post('admin/{tenant:slug}/attendance/corrections/{correction}/approve', [CorrectionController::class, 'approve'])->where('tenant', $notReserved)->whereUuid('correction')->name('tenant.attendance.corrections.approve');
Route::post('admin/{tenant:slug}/attendance/corrections/{correction}/reject', [CorrectionController::class, 'reject'])->where('tenant', $notReserved)->whereUuid('correction')->name('tenant.attendance.corrections.reject');

// The marks made without a connection (criterio 5.4): their tabs are those of the queue above (?status=offline|not_accepted).
// Owner and hr verify one, the selected ones, or say one is incorrect (a correction with the person's note).
Route::post('admin/{tenant:slug}/attendance/offline-marks/verify', [OfflineMarkController::class, 'verifyMany'])->where('tenant', $notReserved)->name('tenant.attendance.offline.verify-many');
Route::post('admin/{tenant:slug}/attendance/offline-marks/{mark}/verify', [OfflineMarkController::class, 'verify'])->where('tenant', $notReserved)->whereUuid('mark')->name('tenant.attendance.offline.verify');
Route::post('admin/{tenant:slug}/attendance/offline-marks/{mark}/reject', [OfflineMarkController::class, 'reject'])->where('tenant', $notReserved)->whereUuid('mark')->name('tenant.attendance.offline.reject');

// RH corrects a mark with no request first, from the report: to another time or voided, always with a note (criterio 5.4).
Route::post('admin/{tenant:slug}/attendance/marks/{mark}/correct', MarkCorrectionController::class)->where('tenant', $notReserved)->whereUuid('mark')->name('tenant.attendance.marks.correct');

// The company's policy: what a mark out of zone does, the accuracy asked of a position, the QR on site, the trusted browser
// and how long the records are kept.
Route::get('admin/{tenant:slug}/attendance/policy', [PolicyController::class, 'show'])->where('tenant', $notReserved)->name('tenant.attendance.policy.show');
Route::put('admin/{tenant:slug}/attendance/policy', [PolicyController::class, 'update'])->where('tenant', $notReserved)->name('tenant.attendance.policy.update');

// The people's agreed weekly schedules (criterio 2.6). {employee} is a person of the company's roster, by id.
Route::get('admin/{tenant:slug}/attendance/schedules', [ScheduleController::class, 'index'])->where('tenant', $notReserved)->name('tenant.attendance.schedules.index');
// The people who have a schedule, searched by name, each with it: to copy one in RH's form («Igual que …»).
Route::get('admin/{tenant:slug}/attendance/schedules/options', [ScheduleController::class, 'options'])->where('tenant', $notReserved)->name('tenant.attendance.schedules.options');
Route::put('admin/{tenant:slug}/attendance/schedules', [ScheduleController::class, 'bulk'])->where('tenant', $notReserved)->name('tenant.attendance.schedules.bulk');
Route::put('admin/{tenant:slug}/attendance/schedules/{employee}', [ScheduleController::class, 'update'])->where('tenant', $notReserved)->whereUuid('employee')->name('tenant.attendance.schedules.update');

// The report for the authority (LFT art. 132-XXXIV): one center and a period, as a CSV for the inspector. Owner and hr only.
Route::get('admin/{tenant:slug}/attendance/report', [ReportController::class, 'show'])->where('tenant', $notReserved)->name('tenant.attendance.report');
Route::get('admin/{tenant:slug}/attendance/report/export', [ReportController::class, 'export'])->where('tenant', $notReserved)->name('tenant.attendance.report.export');
