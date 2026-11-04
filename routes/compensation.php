<?php

declare(strict_types=1);

use App\Modules\Compensation\Http\Controllers\AnnexController;
use App\Modules\Compensation\Http\Controllers\BenefitController;
use App\Modules\Compensation\Http\Controllers\BenefitOptionsController;
use App\Modules\Compensation\Http\Controllers\BenefitTermController;
use App\Modules\Compensation\Http\Controllers\CompensationController;
use App\Modules\Compensation\Http\Controllers\GradeOptionsController;
use App\Modules\Compensation\Http\Controllers\ReportController;
use App\Modules\Compensation\Http\Controllers\ReportShareController;
use App\Modules\Compensation\Http\Controllers\RiskPremiumController;
use App\Modules\Compensation\Http\Controllers\SalaryOverrideController;
use App\Modules\Compensation\Http\Controllers\SalaryOverrideImportController;
use App\Modules\Compensation\Http\Controllers\ScenarioComparisonController;
use App\Modules\Compensation\Http\Controllers\ScenarioController;
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
    Route::get('admin/{tenant:slug}/compensation/overrides', [SalaryOverrideController::class, 'index'])->where('tenant', $notReserved)->name('tenant.compensation.overrides.index');
    Route::post('admin/{tenant:slug}/compensation/overrides', [SalaryOverrideController::class, 'store'])->where('tenant', $notReserved)->name('tenant.compensation.overrides.store');
    Route::get('admin/{tenant:slug}/compensation/overrides/import/template', [SalaryOverrideImportController::class, 'template'])->where('tenant', $notReserved)->name('tenant.compensation.overrides.template');
    Route::post('admin/{tenant:slug}/compensation/overrides/import', [SalaryOverrideImportController::class, 'store'])->where('tenant', $notReserved)->name('tenant.compensation.overrides.import');
    Route::get('admin/{tenant:slug}/compensation/scenarios', [ScenarioController::class, 'index'])->where('tenant', $notReserved)->name('tenant.compensation.scenarios.index');
    Route::get('admin/{tenant:slug}/compensation/scenarios/create', [ScenarioController::class, 'create'])->where('tenant', $notReserved)->name('tenant.compensation.scenarios.create');
    Route::get('admin/{tenant:slug}/compensation/scenarios/compare', ScenarioComparisonController::class)->where('tenant', $notReserved)->name('tenant.compensation.scenarios.compare');
    Route::get('admin/{tenant:slug}/compensation/scenarios/benefits', BenefitOptionsController::class)->where('tenant', $notReserved)->name('tenant.compensation.scenarios.benefits');
    Route::post('admin/{tenant:slug}/compensation/scenarios', [ScenarioController::class, 'store'])->where('tenant', $notReserved)->name('tenant.compensation.scenarios.store');
    Route::get('admin/{tenant:slug}/compensation/scenarios/{scenario}', [ScenarioController::class, 'edit'])->where('tenant', $notReserved)->whereUuid('scenario')->name('tenant.compensation.scenarios.edit');
    Route::put('admin/{tenant:slug}/compensation/scenarios/{scenario}', [ScenarioController::class, 'update'])->where('tenant', $notReserved)->whereUuid('scenario')->name('tenant.compensation.scenarios.update');
    Route::get('admin/{tenant:slug}/compensation/scenarios/{scenario}/report', [ReportController::class, 'show'])->where('tenant', $notReserved)->whereUuid('scenario')->name('tenant.compensation.scenarios.report');
    Route::get('admin/{tenant:slug}/compensation/scenarios/{scenario}/annex', AnnexController::class)->where('tenant', $notReserved)->whereUuid('scenario')->name('tenant.compensation.scenarios.annex');
    Route::post('admin/{tenant:slug}/compensation/scenarios/{scenario}/shares', [ReportShareController::class, 'store'])->where('tenant', $notReserved)->whereUuid('scenario')->name('tenant.compensation.scenarios.shares.store');
    Route::delete('admin/{tenant:slug}/compensation/scenarios/{scenario}/shares/{share}', [ReportShareController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid('scenario')->whereUuid('share')->name('tenant.compensation.scenarios.shares.destroy');
    Route::post('admin/{tenant:slug}/compensation/scenarios/{scenario}/duplicate', [ScenarioController::class, 'duplicate'])->where('tenant', $notReserved)->whereUuid('scenario')->name('tenant.compensation.scenarios.duplicate');
    Route::post('admin/{tenant:slug}/compensation/scenarios/{scenario}/archive', [ScenarioController::class, 'archive'])->where('tenant', $notReserved)->whereUuid('scenario')->name('tenant.compensation.scenarios.archive');

    Route::put('admin/{tenant:slug}/compensation/risk-premium', [RiskPremiumController::class, 'update'])->where('tenant', $notReserved)->name('tenant.compensation.risk-premium.update');
});
