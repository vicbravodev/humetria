<?php

declare(strict_types=1);

use App\Modules\Campaigns\Http\Controllers\SurveyController;
use Illuminate\Support\Facades\Route;

// The employee's survey: no account, the link's token is the credential. Throttled per link and per address
// (CampaignsServiceProvider), so a whole room can answer at once and nobody can try links in bulk.
Route::get('survey/{token}', [SurveyController::class, 'show'])->middleware('throttle:survey-views')->name('survey.show');
Route::post('survey/{token}/{instrument}', [SurveyController::class, 'submit'])->middleware('throttle:survey-answers')->name('survey.submit');
