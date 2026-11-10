<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\AreaOptionsController;
use App\Modules\Organization\Http\Controllers\EmployeeController;
use App\Modules\Organization\Http\Controllers\EmployeeImportController;
use App\Modules\Organization\Http\Controllers\JobGradeOptionsController;
use App\Modules\Organization\Http\Controllers\OrgChartController;
use App\Modules\Organization\Http\Controllers\OrgChartPeopleController;
use App\Modules\Organization\Http\Controllers\OrgChartQualityController;
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
Route::get('admin/{tenant:slug}/areas/options', AreaOptionsController::class)->where('tenant', $notReserved)->name('tenant.areas.options');
Route::get('admin/{tenant:slug}/job-grades/{field}/options', JobGradeOptionsController::class)->where('tenant', $notReserved)->whereIn('field', ['category', 'level'])->name('tenant.job-grades.options');
Route::get('admin/{tenant:slug}/org-chart/people/options', [OrgChartPeopleController::class, 'options'])->where('tenant', $notReserved)->name('tenant.org-chart.people.options');
Route::get('admin/{tenant:slug}/org-chart', [OrgChartController::class, 'index'])->where('tenant', $notReserved)->name('tenant.org-chart.index');
Route::get('admin/{tenant:slug}/org-chart/people/search', [OrgChartPeopleController::class, 'search'])->where('tenant', $notReserved)->name('tenant.org-chart.people.search');
Route::get('admin/{tenant:slug}/org-chart/people/{employee}/reports', [OrgChartController::class, 'reports'])->where('tenant', $notReserved)->whereUuid('employee')->name('tenant.org-chart.reports');
Route::get('admin/{tenant:slug}/org-chart/quality', [OrgChartQualityController::class, 'index'])->where('tenant', $notReserved)->name('tenant.org-chart.quality');
Route::put('admin/{tenant:slug}/org-chart/people/{employee}/manager', [OrgChartQualityController::class, 'update'])->where('tenant', $notReserved)->whereUuid('employee')->name('tenant.org-chart.manager.update');
