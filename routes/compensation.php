<?php

declare(strict_types=1);

use App\Modules\Compensation\Http\Controllers\BenefitController;
use App\Modules\Compensation\Http\Controllers\BenefitTermController;
use App\Modules\Compensation\Http\Controllers\CompensationController;
use App\Modules\Compensation\Http\Controllers\GradeOptionsController;
use App\Modules\Compensation\Http\Controllers\RiskPremiumController;
use App\Modules\Compensation\Http\Controllers\TabulatorController;
use App\Modules\Compensation\Http\Controllers\TabulatorImportController;
use App\Modules\Compensation\Http\Middleware\BlockWhileImpersonating;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/compensation.
// Every name starts with tenant.compensation.; all of them answer 404 while a superadmin impersonates the owner.
/** @var string $notReserved */
Route::middleware(BlockWhileImpersonating::class)->group(function () use ($notReserved): void {
    Route::get('admin/{tenant:slug}/compensation', [CompensationController::class, 'index'])->where('tenant', $notReserved)->name('tenant.compensation.index');

    Route::get('admin/{tenant:slug}/compensation/benefits', [BenefitController::class, 'index'])->where('tenant', $notReserved)->name('tenant.compensation.benefits.index');
    Route::post('admin/{tenant:slug}/compensation/benefits', [BenefitController::class, 'store'])->where('tenant', $notReserved)->name('tenant.compensation.benefits.store');
    Route::get('admin/{tenant:slug}/compensation/benefits/grades', GradeOptionsController::class)->where('tenant', $notReserved)->name('tenant.compensation.benefits.grades');
    Route::put('admin/{tenant:slug}/compensation/benefits/{benefit}', [BenefitController::class, 'update'])->where('tenant', $notReserved)->whereUuid('benefit')->name('tenant.compensation.benefits.update');
    Route::post('admin/{tenant:slug}/compensation/benefits/{benefit}/archive', [BenefitController::class, 'archive'])->where('tenant', $notReserved)->whereUuid('benefit')->name('tenant.compensation.benefits.archive');
    Route::post('admin/{tenant:slug}/compensation/benefits/{benefit}/restore', [BenefitController::class, 'restore'])->where('tenant', $notReserved)->whereUuid('benefit')->name('tenant.compensation.benefits.restore');
    Route::post('admin/{tenant:slug}/compensation/benefits/{benefit}/terms', [BenefitTermController::class, 'store'])->where('tenant', $notReserved)->whereUuid('benefit')->name('tenant.compensation.benefits.terms.store');
    Route::get('admin/{tenant:slug}/compensation/tabulator', [TabulatorController::class, 'index'])->where('tenant', $notReserved)->name('tenant.compensation.tabulator.index');
    Route::post('admin/{tenant:slug}/compensation/tabulator', [TabulatorController::class, 'store'])->where('tenant', $notReserved)->name('tenant.compensation.tabulator.store');
    Route::get('admin/{tenant:slug}/compensation/tabulator/import/template', [TabulatorImportController::class, 'template'])->where('tenant', $notReserved)->name('tenant.compensation.tabulator.template');
    Route::post('admin/{tenant:slug}/compensation/tabulator/import', [TabulatorImportController::class, 'store'])->where('tenant', $notReserved)->name('tenant.compensation.tabulator.import');
    Route::put('admin/{tenant:slug}/compensation/risk-premium', [RiskPremiumController::class, 'update'])->where('tenant', $notReserved)->name('tenant.compensation.risk-premium.update');
});
