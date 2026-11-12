<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/first-steps.
// Every name starts with tenant.first-steps. Only the owner may use them (FirstStepsStatePolicy).

use App\Modules\FirstSteps\Http\Controllers\FirstStepsStateController;
use Illuminate\Support\Facades\Route;

/** @var string $notReserved */
Route::post('admin/{tenant:slug}/first-steps/welcome', [FirstStepsStateController::class, 'welcome'])->where('tenant', $notReserved)->name('tenant.first-steps.welcome');
Route::post('admin/{tenant:slug}/first-steps/hide', [FirstStepsStateController::class, 'hide'])->where('tenant', $notReserved)->name('tenant.first-steps.hide');
Route::post('admin/{tenant:slug}/first-steps/show', [FirstStepsStateController::class, 'show'])->where('tenant', $notReserved)->name('tenant.first-steps.show');
