<?php

declare(strict_types=1);

use App\Modules\Interventions\Http\Controllers\ActionController;
use App\Modules\Interventions\Http\Controllers\ProgramController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}. The Programa de
// intervención hangs from the closed evaluation it comes from. Nothing of it is deleted (criterio C-35).
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/campaigns/{campaign}/program', [ProgramController::class, 'show'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.program');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/program', [ProgramController::class, 'store'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.program.store');
Route::put('admin/{tenant:slug}/campaigns/{campaign}/program/responsible', [ProgramController::class, 'responsible'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.program.responsible');

// The actions of the Programa, added to a row and edited. An action is cancelled with a reason, never deleted.
Route::post('admin/{tenant:slug}/campaigns/{campaign}/program/items/{item}/actions', [ActionController::class, 'store'])->where('tenant', $notReserved)->whereUuid(['campaign', 'item'])->name('tenant.campaigns.program.actions.store');
Route::put('admin/{tenant:slug}/campaigns/{campaign}/program/actions/{action}', [ActionController::class, 'update'])->where('tenant', $notReserved)->whereUuid(['campaign', 'action'])->name('tenant.campaigns.program.actions.update');
