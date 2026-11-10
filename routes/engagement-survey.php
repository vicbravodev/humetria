<?php

declare(strict_types=1);

use App\Modules\Engagement\Http\Controllers\RespondentController;
use Illuminate\Support\Facades\Route;

// Required from routes/web.php: the link of a climate or pulse survey, outside the company group. The token of the link
// is the credential. Every name starts with engagement.survey. Throttled per link and per address
// (EngagementServiceProvider), so a whole plant can answer at once and nobody can try links in bulk.
Route::get('engagement/{token}', [RespondentController::class, 'show'])->middleware('throttle:engagement-views')->name('engagement.survey.show');
Route::post('engagement/{token}', [RespondentController::class, 'submit'])->middleware('throttle:engagement-answers')->name('engagement.survey.submit');
