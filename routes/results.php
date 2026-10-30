<?php

declare(strict_types=1);

use App\Modules\Results\Http\Controllers\ReportController;
use App\Modules\Results\Http\Controllers\ReportShareController;
use App\Modules\Results\Http\Controllers\ResultsController;
use App\Modules\Results\Http\Controllers\ScreeningController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/campaigns/{campaign}/results', [ResultsController::class, 'show'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.results');
Route::get('admin/{tenant:slug}/campaigns/{campaign}/screenings', [ScreeningController::class, 'index'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.screenings');
Route::get('admin/{tenant:slug}/campaigns/{campaign}/report', [ReportController::class, 'show'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.report');
Route::put('admin/{tenant:slug}/campaigns/{campaign}/report', [ReportController::class, 'update'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.report.update');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/shares', [ReportShareController::class, 'store'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.shares.store');
Route::delete('admin/{tenant:slug}/campaigns/{campaign}/shares/{share}', [ReportShareController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid(['campaign', 'share'])->name('tenant.campaigns.shares.destroy');
