<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Disclosure\Http\Controllers\PortalPolicyController;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Support\Facades\Route;

// The policy page of the person, in their pages: the company of the slug (a company without NOM-035 answers 404), then their
// session (a visitor goes to enter, and comes back here). Throttled per session and per address (portal-views and
// portal-actions).
Route::middleware([ResolveWorkerTenant::class, RequirePortalTool::class.':nom035', RequireWorkerSession::class])->prefix('me/{tenant:slug}/policy')->group(function (): void {
    Route::get('/', [PortalPolicyController::class, 'show'])->middleware('throttle:portal-views')->name('portal.policy.show');
    Route::post('acknowledge', [PortalPolicyController::class, 'acknowledge'])->middleware('throttle:portal-actions')->name('portal.policy.acknowledge');
});
