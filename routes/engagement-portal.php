<?php

declare(strict_types=1);

use App\Modules\Engagement\Http\Controllers\PortalClimateController;
use App\Modules\Engagement\Http\Controllers\PortalFeedbackController;
use Illuminate\Support\Facades\Route;

// Required from routes/web.php: the feedback a manager reads and the climate of the team in the employee portal, outside
// the company group. Every name starts with portal.climate.

// «Nos dijeron / Haremos»: no account, the link's token is the credential (the same as NOM-037's). Throttled per link and
// per address (TeleworkServiceProvider's portal-views).
Route::get('portal/{token}/climate', [PortalClimateController::class, 'show'])->middleware('throttle:portal-views')->name('portal.climate.show');
// The manager's feedback: the aggregate of their group and the AI summary of its comments. The token is the credential
// and says whose group it is; there is no route to ask for, redo or regenerate a summary.
Route::get('portal/{token}/feedback', [PortalFeedbackController::class, 'show'])->middleware('throttle:portal-views')->name('portal.feedback.show');
