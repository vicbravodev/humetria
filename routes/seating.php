<?php

declare(strict_types=1);

use App\Modules\Seating\Http\Controllers\PositionController;
use App\Modules\Seating\Http\Controllers\SeatController;
use App\Modules\Seating\Http\Controllers\SeatingController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/seating.
// Every name starts with tenant.seating.: it is how the committee role (LimitCommitteeToTelework) is let in.
// Nothing of Ley Silla is deleted (criterio C-17): the only DELETE routes end an assignment or cancel a measure.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/seating', [SeatingController::class, 'index'])->where('tenant', $notReserved)->name('tenant.seating.index');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}', [SeatingController::class, 'center'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.center');
Route::put('admin/{tenant:slug}/seating/centers/{workCenter}/profile', [SeatingController::class, 'updateProfile'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.profile.update');

// The positions where people work standing (Figura 1) and who holds them. An assignment is ended, not deleted.
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/positions/create', [PositionController::class, 'create'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.positions.create');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/positions', [PositionController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.positions.store');
Route::get('admin/{tenant:slug}/seating/positions/{position}', [PositionController::class, 'show'])->where('tenant', $notReserved)->whereUuid('position')->name('tenant.seating.positions.show');
Route::put('admin/{tenant:slug}/seating/positions/{position}', [PositionController::class, 'update'])->where('tenant', $notReserved)->whereUuid('position')->name('tenant.seating.positions.update');
Route::get('admin/{tenant:slug}/seating/positions/{position}/employees', [PositionController::class, 'employees'])->where('tenant', $notReserved)->whereUuid('position')->name('tenant.seating.positions.employees');
Route::post('admin/{tenant:slug}/seating/positions/{position}/assignments', [PositionController::class, 'assign'])->where('tenant', $notReserved)->whereUuid('position')->name('tenant.seating.positions.assignments.store');
Route::delete('admin/{tenant:slug}/seating/assignments/{assignment}', [PositionController::class, 'unassign'])->where('tenant', $notReserved)->whereUuid('assignment')->name('tenant.seating.assignments.destroy');

// The seat inventory of a center. A seat is registered and edited, never deleted; its photo leaves only through the app.
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/seats', [SeatController::class, 'index'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.seats.index');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/seats', [SeatController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.seats.store');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/seats/positions', [SeatController::class, 'positions'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.seats.positions');
Route::put('admin/{tenant:slug}/seating/seats/{seat}', [SeatController::class, 'update'])->where('tenant', $notReserved)->whereUuid('seat')->name('tenant.seating.seats.update');
Route::get('admin/{tenant:slug}/seating/seats/{seat}/photo', [SeatController::class, 'photo'])->where('tenant', $notReserved)->whereUuid('seat')->name('tenant.seating.seats.photo');
