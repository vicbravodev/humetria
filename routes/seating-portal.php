<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Seating\Http\Controllers\PortalAcknowledgmentController;
use App\Modules\Seating\Http\Controllers\PortalController;
use App\Modules\Seating\Http\Controllers\SeatReportController;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Support\Facades\Route;

// The Ley Silla page of the person, in their pages: the company of the slug (a company without Ley Silla answers 404), then
// their session (a visitor goes to enter, and comes back here). Throttled per session and per address (portal-views and
// portal-actions). Confirming the risks takes a recent proof that it is the person (worker.proof).
Route::middleware([ResolveWorkerTenant::class, RequirePortalTool::class.':seating', RequireWorkerSession::class])->prefix('me/{tenant:slug}/seating')->group(function (): void {
    Route::get('/', [PortalController::class, 'show'])->middleware('throttle:portal-views')->name('portal.seating.show');
    Route::post('inputs', [PortalController::class, 'store'])->middleware('throttle:portal-actions')->name('portal.seating.inputs.store');
    Route::post('acknowledgments', [PortalAcknowledgmentController::class, 'store'])->middleware(['throttle:portal-actions', 'worker.proof'])->name('portal.seating.acknowledgments.store');
    Route::post('reports', [SeatReportController::class, 'store'])->middleware('throttle:portal-actions')->name('portal.seating.reports.store');
});
