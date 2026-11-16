<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Results\Http\Controllers\PortalSupportController;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Support\Facades\Route;

// The support message of the person (board V4-N035-Canalizacion), in their pages: the company of the slug (a company without
// NOM-035 answers 404), then their session (a visitor goes to enter, and comes back here), then a recent proof that it is
// the person (worker.proof: the message leads to an identified Guía I referral, the most delicate page of the person).
// Throttled per session and per address (portal-views).
Route::middleware([ResolveWorkerTenant::class, RequirePortalTool::class.':nom035', RequireWorkerSession::class])->prefix('me/{tenant:slug}')->group(function (): void {
    Route::get('support', [PortalSupportController::class, 'show'])->middleware(['throttle:portal-views', 'worker.proof'])->name('portal.support.show');
});
