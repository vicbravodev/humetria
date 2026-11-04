<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\EndRevokedImpersonation;
use App\Http\Middleware\ForbidWhileImpersonating;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Disclosure\Http\Controllers\WorkersPageController;
use App\Modules\Disclosure\Http\Middleware\HandleWorkersPageRequests;
use App\Modules\Disclosure\Http\Middleware\WorkersPageResponse;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The page of the workers (/workers/{token}): no account and, above all, no session. The session table keeps the address and
// the browser of every visit, and a cookie is a thread that ties one visit to the next, so none of that middleware runs here
// and nobody is recorded for opening a poster (criterio C-55). It only reads, so there is nothing to forge. The token is the
// credential of the company; it is throttled per address and per link by `workers-page` (DisclosureServiceProvider).
Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    EndRevokedImpersonation::class,
    AssignRequestContext::class,
    ForbidWhileImpersonating::class,
    HandleAppearance::class,
    HandleInertiaRequests::class,
])->middleware([HandleWorkersPageRequests::class, WorkersPageResponse::class, 'throttle:workers-page'])->group(function (): void {
    Route::get('workers/{token}', [WorkersPageController::class, 'show'])->name('workers.show');
    Route::get('workers/{token}/centers/{workCenter}', [WorkersPageController::class, 'center'])->name('workers.center');
});
