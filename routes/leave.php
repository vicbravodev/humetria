<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/vacations.
// Every name starts with tenant.vacations.

use App\Modules\Leave\Http\Controllers\RequestController;
use App\Modules\Leave\Http\Controllers\VacationsController;
use Illuminate\Support\Facades\Route;

/** @var string $notReserved */
// RH's overview: who is out, what waits for an answer, the windows about to close and the days owed.
Route::get('admin/{tenant:slug}/vacations', [VacationsController::class, 'index'])->where('tenant', $notReserved)->name('tenant.vacations.index');

// RH's queue: what reached them first, then what waits for the manager and what was answered.
Route::get('admin/{tenant:slug}/vacations/requests', [RequestController::class, 'index'])->where('tenant', $notReserved)->name('tenant.vacations.requests.index');

// RH answers a request at any time, or corrects one by cancelling it. Owner and hr; a viewer reads the queue only.
Route::post('admin/{tenant:slug}/vacations/requests/{leaveRequest}/approve', [RequestController::class, 'approve'])->where('tenant', $notReserved)->whereUuid('leaveRequest')->name('tenant.vacations.requests.approve');
Route::post('admin/{tenant:slug}/vacations/requests/{leaveRequest}/reject', [RequestController::class, 'reject'])->where('tenant', $notReserved)->whereUuid('leaveRequest')->name('tenant.vacations.requests.reject');
Route::post('admin/{tenant:slug}/vacations/requests/{leaveRequest}/cancel', [RequestController::class, 'cancel'])->where('tenant', $notReserved)->whereUuid('leaveRequest')->name('tenant.vacations.requests.cancel');
