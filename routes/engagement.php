<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: the panel of the climate survey lives under
// /admin/{tenant}/climate. Every name starts with tenant.climate.

use App\Modules\Engagement\Http\Controllers\ResultsController;
use Illuminate\Support\Facades\Route;

/** @var string $notReserved */
// The results of a closed survey: the company, or one cut at a time (?cut=work_center|area|tenure|manager).
Route::get('admin/{tenant:slug}/climate/{survey}/results', [ResultsController::class, 'show'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', 'climate')->name('tenant.climate.results');
