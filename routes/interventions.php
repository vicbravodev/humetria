<?php

declare(strict_types=1);

use App\Modules\Interventions\Http\Controllers\ProgramController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}. The Programa de
// intervención hangs from the closed evaluation it comes from. Nothing of it is deleted (criterio C-35).
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/campaigns/{campaign}/program', [ProgramController::class, 'show'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.program');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/program', [ProgramController::class, 'store'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.program.store');
