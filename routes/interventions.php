<?php

declare(strict_types=1);

use App\Modules\Interventions\Http\Controllers\ActionController;
use App\Modules\Interventions\Http\Controllers\ItemController;
use App\Modules\Interventions\Http\Controllers\ProgramController;
use App\Modules\Interventions\Http\Controllers\ProgramListController;
use App\Modules\Interventions\Http\Controllers\ProgressController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}. The Programa de
// intervención hangs from the closed evaluation it comes from. Nothing of it is deleted (criterio C-35).
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/programs', [ProgramListController::class, 'index'])->where('tenant', $notReserved)->name('tenant.interventions.index');
Route::get('admin/{tenant:slug}/campaigns/{campaign}/program', [ProgramController::class, 'show'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.program');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/program', [ProgramController::class, 'store'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.program.store');
Route::put('admin/{tenant:slug}/campaigns/{campaign}/program/responsible', [ProgramController::class, 'responsible'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.program.responsible');

// The actions of the Programa, added to a row and edited. An action is cancelled with a reason, never deleted.
Route::post('admin/{tenant:slug}/campaigns/{campaign}/program/items/{item}/actions', [ActionController::class, 'store'])->where('tenant', $notReserved)->whereUuid(['campaign', 'item'])->name('tenant.campaigns.program.actions.store');
Route::put('admin/{tenant:slug}/campaigns/{campaign}/program/actions/{action}', [ActionController::class, 'update'])->where('tenant', $notReserved)->whereUuid(['campaign', 'action'])->name('tenant.campaigns.program.actions.update');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/program/actions/{action}/copies', [ActionController::class, 'copy'])->where('tenant', $notReserved)->whereUuid(['campaign', 'action'])->name('tenant.campaigns.program.actions.copy');

// The control of progress (num. 8.4 d): a step is recorded, never edited or deleted; an action is cancelled with a reason.
Route::post('admin/{tenant:slug}/campaigns/{campaign}/program/actions/{action}/progress', [ProgressController::class, 'store'])->where('tenant', $notReserved)->whereUuid(['campaign', 'action'])->name('tenant.campaigns.program.progress.store');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/program/actions/{action}/cancel', [ProgressController::class, 'cancel'])->where('tenant', $notReserved)->whereUuid(['campaign', 'action'])->name('tenant.campaigns.program.actions.cancel');
Route::get('admin/{tenant:slug}/campaigns/{campaign}/program/actions/{action}/progress/{entry}/evidence', [ProgressController::class, 'evidence'])->where('tenant', $notReserved)->whereUuid(['campaign', 'action', 'entry'])->name('tenant.campaigns.program.evidence');

// The rows: HR adds one from the catalog of the guide, writes its analysis and its follow-up evaluation (num. 8.4 e).
Route::post('admin/{tenant:slug}/campaigns/{campaign}/program/items', [ItemController::class, 'store'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.program.items.store');
Route::put('admin/{tenant:slug}/campaigns/{campaign}/program/items/{item}/analysis', [ItemController::class, 'analysis'])->where('tenant', $notReserved)->whereUuid(['campaign', 'item'])->name('tenant.campaigns.program.items.analysis');
Route::put('admin/{tenant:slug}/campaigns/{campaign}/program/items/{item}/follow-up', [ItemController::class, 'followUp'])->where('tenant', $notReserved)->whereUuid(['campaign', 'item'])->name('tenant.campaigns.program.items.follow-up');
