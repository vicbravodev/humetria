<?php

declare(strict_types=1);

use App\Modules\Compensation\Http\Controllers\SharedReportController;
use Illuminate\Support\Facades\Route;

// The report of a scenario opened from a temporary link: no account, the link's token is the credential. Throttled per link
// and per address (CompensationServiceProvider).
Route::get('compensation-report/{token}', [SharedReportController::class, 'show'])->middleware('throttle:compensation-report-links')->name('compensation.report.shared');
