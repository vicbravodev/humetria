<?php

declare(strict_types=1);

// Required from routes/web.php: the vacations of a person and of their team in the person's pages (`/me/{slug}`), outside
// the company group. Every name starts with portal.vacations.

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Leave\Http\Controllers\PortalStatementController;
use App\Modules\Leave\Http\Controllers\PortalTeamRequestsController;
use App\Modules\Leave\Http\Controllers\PortalVacationsController;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Support\Facades\Route;

// The company of the slug (a company without Vacaciones answers 404), then the person's session (a visitor goes to enter,
// and comes back here). Throttled per session and per address (portal-views and portal-actions).
Route::middleware([ResolveWorkerTenant::class, RequirePortalTool::class.':vacations', RequireWorkerSession::class])->prefix('me/{tenant:slug}/vacations')->group(function (): void {
    Route::get('/', [PortalVacationsController::class, 'show'])->middleware('throttle:portal-views')->name('portal.vacations.show');

    // Asking for days: what the dates would be before sending them, sending the request, and cancelling one of their own.
    Route::get('preview', [PortalVacationsController::class, 'preview'])->middleware('throttle:portal-views')->name('portal.vacations.preview');
    Route::post('requests', [PortalVacationsController::class, 'store'])->middleware('throttle:portal-actions')->name('portal.vacations.requests.store');
    Route::post('requests/{request}/cancel', [PortalVacationsController::class, 'cancel'])->whereUuid('request')->middleware('throttle:portal-actions')->name('portal.vacations.requests.cancel');

    // A manager answers the requests that wait for them: the session says who they are, and one that is not theirs to answer is a 404.
    Route::get('team', [PortalTeamRequestsController::class, 'show'])->middleware('throttle:portal-views')->name('portal.vacations.team.show');
    Route::post('team/{request}/approve', [PortalTeamRequestsController::class, 'approve'])->whereUuid('request')->middleware('throttle:portal-actions')->name('portal.vacations.team.approve');
    Route::post('team/{request}/reject', [PortalTeamRequestsController::class, 'reject'])->whereUuid('request')->middleware('throttle:portal-actions')->name('portal.vacations.team.reject');

    // The annual statement of a period (LFT art. 81): read it and confirm they received it. One that is not theirs is a 404.
    Route::get('statements/{statement}', [PortalStatementController::class, 'show'])->whereUuid('statement')->middleware('throttle:portal-views')->name('portal.vacations.statements.show');
    Route::post('statements/{statement}/acknowledge', [PortalStatementController::class, 'acknowledge'])->whereUuid('statement')->middleware('throttle:portal-actions')->name('portal.vacations.statements.acknowledge');
});
