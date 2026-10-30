<?php

declare(strict_types=1);

use App\Modules\Campaigns\Http\Controllers\SurveyController;
use Illuminate\Support\Facades\Route;

// The employee's survey: no account, the link's token is the credential. Throttled, so tokens cannot be guessed.
Route::get('survey/{token}', [SurveyController::class, 'show'])->middleware('throttle:60,1')->name('survey.show');
Route::post('survey/{token}/{instrument}', [SurveyController::class, 'submit'])->middleware('throttle:20,1')->name('survey.submit');
