<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\EmployeeController;
use App\Modules\Organization\Http\Controllers\EmployeeImportController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/employees', [EmployeeController::class, 'index'])->where('tenant', $notReserved)->name('tenant.employees.index');
Route::post('admin/{tenant:slug}/employees', [EmployeeController::class, 'store'])->where('tenant', $notReserved)->name('tenant.employees.store');
Route::put('admin/{tenant:slug}/employees/{employee}', [EmployeeController::class, 'update'])->where('tenant', $notReserved)->whereUuid('employee')->name('tenant.employees.update');
Route::post('admin/{tenant:slug}/employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->where('tenant', $notReserved)->whereUuid('employee')->name('tenant.employees.deactivate');
Route::post('admin/{tenant:slug}/employees/{employee}/reactivate', [EmployeeController::class, 'reactivate'])->where('tenant', $notReserved)->whereUuid('employee')->name('tenant.employees.reactivate');
Route::get('admin/{tenant:slug}/employees/import', [EmployeeImportController::class, 'create'])->where('tenant', $notReserved)->name('tenant.employees.import.create');
Route::get('admin/{tenant:slug}/employees/import/template', [EmployeeImportController::class, 'template'])->where('tenant', $notReserved)->name('tenant.employees.import.template');
Route::post('admin/{tenant:slug}/employees/import', [EmployeeImportController::class, 'store'])->where('tenant', $notReserved)->name('tenant.employees.import.store');
