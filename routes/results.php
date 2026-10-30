<?php

declare(strict_types=1);

use App\Modules\Results\Http\Controllers\ResultsController;
use App\Modules\Results\Http\Controllers\ScreeningController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/campaigns/{campaign}/results', [ResultsController::class, 'show'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.results');
Route::get('admin/{tenant:slug}/campaigns/{campaign}/screenings', [ScreeningController::class, 'index'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.screenings');
