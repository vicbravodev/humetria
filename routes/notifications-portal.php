<?php

declare(strict_types=1);

// Required from routes/web.php: the push notices of a person in the person's pages (`/me/{slug}`), outside the company group. Every
// name starts with portal.push.

use App\Modules\Notifications\Http\Controllers\PushSubscriptionController;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Support\Facades\Route;

// The company of the slug, then the person's session (a visitor gets a 401 in JSON). Subscribing shows nothing, so it asks no recent
// proof (worker.proof). Throttled per session and per address (portal-views and portal-actions).
Route::middleware([ResolveWorkerTenant::class, RequireWorkerSession::class])->prefix('me/{tenant:slug}/push')->group(function (): void {
    Route::get('/', [PushSubscriptionController::class, 'show'])->middleware('throttle:portal-views')->name('portal.push.show');
    Route::post('/', [PushSubscriptionController::class, 'store'])->middleware('throttle:portal-actions')->name('portal.push.store');
    Route::delete('/', [PushSubscriptionController::class, 'destroy'])->middleware('throttle:portal-actions')->name('portal.push.destroy');
});
