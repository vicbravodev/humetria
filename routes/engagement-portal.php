<?php

declare(strict_types=1);

use App\Modules\Engagement\Http\Controllers\PortalClimateController;
use App\Modules\Engagement\Http\Controllers\PortalFeedbackController;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Support\Facades\Route;

// Required from routes/web.php: the feedback a manager reads and the climate of the team in the person's pages (`/me/{slug}`),
// outside the company group. The company of the slug, then the person's session (a visitor goes to enter, and comes back
// here). Throttled per session and per address (portal-views).
Route::middleware([ResolveWorkerTenant::class, RequireWorkerSession::class])->prefix('me/{tenant:slug}')->group(function (): void {
    // «Nos dijeron / Haremos».
    Route::get('climate', [PortalClimateController::class, 'show'])->middleware('throttle:portal-views')->name('portal.climate.show');
    // The manager's feedback: the aggregate of their group and the AI summary of its comments. The session says whose group
    // it is; there is no route to ask for, redo or regenerate a summary.
    Route::get('feedback', [PortalFeedbackController::class, 'show'])->middleware('throttle:portal-views')->name('portal.feedback.show');
});
