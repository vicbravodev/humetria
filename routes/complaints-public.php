<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\EndRevokedImpersonation;
use App\Http\Middleware\ForbidWhileImpersonating;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Complaints\Http\Controllers\PublicCentersController;
use App\Modules\Complaints\Http\Controllers\PublicChannelController;
use App\Modules\Complaints\Http\Controllers\PublicComplaintController;
use App\Modules\Complaints\Http\Middleware\GuardPublicSubmission;
use App\Modules\Complaints\Http\Middleware\HandlePublicChannelRequests;
use App\Modules\Complaints\Http\Middleware\OpenChannelForSubmission;
use App\Modules\Complaints\Http\Middleware\PrivateChannelResponse;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The public channel of complaints and reports (/c/{code}): no account and, above all, no session. The session table keeps the
// address and the browser of every visit, and a cookie is a thread that ties the visits together, so none of that
// middleware runs here (criterio C-46). The forgery check of the session is replaced by a signed ticket. The code is the
// credential of the company; the reference and key of a complaint, the credential of the person.
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
])->middleware([HandlePublicChannelRequests::class, PrivateChannelResponse::class])->group(function (): void {
    Route::get('c/{code}', [PublicChannelController::class, 'show'])->middleware('throttle:complaints-view')->name('complaints.public.show');
    Route::get('c/{code}/centers', PublicCentersController::class)
        ->middleware(['throttle:complaints-view', OpenChannelForSubmission::class])
        ->name('complaints.public.centers');
    Route::post('c/{code}', [PublicComplaintController::class, 'store'])
        ->middleware(['throttle:complaints-submit', OpenChannelForSubmission::class, GuardPublicSubmission::class])
        ->name('complaints.public.store');
});
