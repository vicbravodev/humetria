<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Organization\Http\Controllers\PortalTeamController;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Support\Facades\Route;

// «Mi equipo» in the person's pages: the company of the slug (a company without the org chart answers 404), then their
// session (a visitor goes to enter, and comes back here). Throttled per session and per address (portal-views).
Route::get('me/{tenant:slug}/team', [PortalTeamController::class, 'show'])
    ->middleware([ResolveWorkerTenant::class, RequirePortalTool::class.':org_chart', RequireWorkerSession::class, 'throttle:portal-views'])
    ->name('portal.team.show');
