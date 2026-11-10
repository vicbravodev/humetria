<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/vacations.
// Every name starts with tenant.vacations.

use App\Modules\Leave\Http\Controllers\BalanceController;
use App\Modules\Leave\Http\Controllers\CalendarController;
use App\Modules\Leave\Http\Controllers\EmployeeRecordController;
use App\Modules\Leave\Http\Controllers\PolicyController;
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

// RH records vacations or a leave for somebody, and looks the person up by name.
Route::post('admin/{tenant:slug}/vacations/requests', [RequestController::class, 'store'])->where('tenant', $notReserved)->name('tenant.vacations.requests.store');
Route::get('admin/{tenant:slug}/vacations/people/options', [RequestController::class, 'people'])->where('tenant', $notReserved)->name('tenant.vacations.people.options');

// The days of everybody, and the link to their own vacations that RH sends to some of them.
Route::get('admin/{tenant:slug}/vacations/balances', [BalanceController::class, 'index'])->where('tenant', $notReserved)->name('tenant.vacations.balances.index');
Route::post('admin/{tenant:slug}/vacations/balances/links', [BalanceController::class, 'links'])->where('tenant', $notReserved)->name('tenant.vacations.balances.links');

// The record of one person: periods, ledger, requests, statements and, if they left, what they are owed in days.
Route::get('admin/{tenant:slug}/vacations/employees/{employee}', [EmployeeRecordController::class, 'show'])->where('tenant', $notReserved)->whereUuid('employee')->name('tenant.vacations.employees.show');

// RH corrects a tranche of a period with its note, and settles the days of a person who left. Owner and hr.
Route::post('admin/{tenant:slug}/vacations/employees/{employee}/adjust', [EmployeeRecordController::class, 'adjust'])->where('tenant', $notReserved)->whereUuid('employee')->name('tenant.vacations.employees.adjust');
Route::post('admin/{tenant:slug}/vacations/terminations/{termination}/settle', [EmployeeRecordController::class, 'settle'])->where('tenant', $notReserved)->whereUuid('termination')->name('tenant.vacations.terminations.settle');

// The team's calendar of a month, by work center and area.
Route::get('admin/{tenant:slug}/vacations/calendar', [CalendarController::class, 'index'])->where('tenant', $notReserved)->name('tenant.vacations.calendar');

// The policies of the company: the law first, then its own, which can only improve it. Owner and hr write; a viewer reads.
Route::get('admin/{tenant:slug}/vacations/policies', [PolicyController::class, 'index'])->where('tenant', $notReserved)->name('tenant.vacations.policies.index');
Route::get('admin/{tenant:slug}/vacations/policies/create', [PolicyController::class, 'create'])->where('tenant', $notReserved)->name('tenant.vacations.policies.create');
Route::post('admin/{tenant:slug}/vacations/policies', [PolicyController::class, 'store'])->where('tenant', $notReserved)->name('tenant.vacations.policies.store');
Route::get('admin/{tenant:slug}/vacations/policies/{policy}/edit', [PolicyController::class, 'edit'])->where('tenant', $notReserved)->whereUuid('policy')->name('tenant.vacations.policies.edit');
Route::put('admin/{tenant:slug}/vacations/policies/{policy}', [PolicyController::class, 'update'])->where('tenant', $notReserved)->whereUuid('policy')->name('tenant.vacations.policies.update');
Route::post('admin/{tenant:slug}/vacations/policies/{policy}/archive', [PolicyController::class, 'archive'])->where('tenant', $notReserved)->whereUuid('policy')->name('tenant.vacations.policies.archive');
