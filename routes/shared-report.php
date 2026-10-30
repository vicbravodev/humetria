<?php

declare(strict_types=1);

use App\Modules\Results\Http\Controllers\SharedReportController;
use Illuminate\Support\Facades\Route;

// An evaluation's report opened from a temporary link: no account, the link's token is the credential. Throttled per
// link and per address (ResultsServiceProvider).
Route::get('report/{token}', [SharedReportController::class, 'show'])->middleware('throttle:report-links')->name('report.shared');
