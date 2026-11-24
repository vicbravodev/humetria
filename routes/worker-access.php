<?php

declare(strict_types=1);

// Required from routes/web.php: the pages of the person who works (no account), under me/{tenant:slug}. Every name starts
// with worker.

use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\EndRevokedImpersonation;
use App\Http\Middleware\ForbidWhileImpersonating;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\WorkerAccess\Http\Controllers\AccessLinkController;
use App\Modules\WorkerAccess\Http\Controllers\EntryController;
use App\Modules\WorkerAccess\Http\Controllers\HomeController;
use App\Modules\WorkerAccess\Http\Controllers\InstallableAppController;
use App\Modules\WorkerAccess\Http\Controllers\KeyController;
use App\Modules\WorkerAccess\Http\Controllers\NotMeController;
use App\Modules\WorkerAccess\Http\Controllers\ProofController;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The company of the slug (ResolveWorkerTenant: an unknown or blocked one gets the same 404). The address carries no
// credential: the person's session is, or the signature of their key. Per address, and per key where one signs.
Route::middleware(['throttle:worker-enter', ResolveWorkerTenant::class])->group(function (): void {
    Route::get('me/{tenant:slug}', HomeController::class)->name('worker.home');
    Route::post('me/{tenant:slug}/enter/options', [EntryController::class, 'options'])->name('worker.enter.options');
    Route::post('me/{tenant:slug}/enter', [EntryController::class, 'store'])->name('worker.enter');
    Route::post('me/{tenant:slug}/leave', [EntryController::class, 'leave'])->name('worker.leave');
});

// Asking for a link by email: per address and per email (only its hash names the counter).
Route::middleware(['throttle:worker-link-request', ResolveWorkerTenant::class])->group(function (): void {
    Route::post('me/{tenant:slug}/enter/link', [AccessLinkController::class, 'request'])->name('worker.link.request');
});

// A one-time link: the token in the address is the credential, good once. Per address and per link.
Route::middleware(['throttle:worker-link', ResolveWorkerTenant::class])->group(function (): void {
    Route::get('me/{tenant:slug}/link/{token}', [AccessLinkController::class, 'show'])->name('worker.link.show');
    Route::post('me/{tenant:slug}/link/{token}', [AccessLinkController::class, 'open'])->name('worker.link.open');
});

// Registering a key takes the person's session (checked by the controller: a link RH issued or a key; never a link the person
// asked for). Per address.
Route::middleware(['throttle:worker-key', ResolveWorkerTenant::class])->group(function (): void {
    Route::get('me/{tenant:slug}/key', [KeyController::class, 'show'])->name('worker.key.show');
    Route::post('me/{tenant:slug}/key/options', [KeyController::class, 'options'])->name('worker.key.options');
    Route::post('me/{tenant:slug}/key', [KeyController::class, 'store'])->name('worker.key.store');
});

// «No fui yo», from the email of a new key: the address the app signed is the credential (a missing or altered signature, 403).
Route::middleware(['throttle:worker-enter', 'signed:relative', ResolveWorkerTenant::class])->group(function (): void {
    Route::get('me/{tenant:slug}/not-me/{credential}', [NotMeController::class, 'show'])->whereUuid('credential')->name('worker.not-me.show');
    Route::post('me/{tenant:slug}/not-me/{credential}', [NotMeController::class, 'confirm'])->whereUuid('credential')->name('worker.not-me.confirm');
});

// A recent proof before something delicate (RequireRecentProof, alias worker.proof): the person's key signs, or a person
// without a key writes the code we emailed. Takes the person's session (a visitor goes to enter). Per address and per session.
Route::middleware(['throttle:worker-proof', ResolveWorkerTenant::class, RequireWorkerSession::class])->prefix('me/{tenant:slug}/proof')->group(function (): void {
    Route::get('/', [ProofController::class, 'show'])->name('worker.proof.show');
    Route::post('options', [ProofController::class, 'options'])->name('worker.proof.options');
    Route::post('/', [ProofController::class, 'store'])->name('worker.proof.store');
    Route::post('code', [ProofController::class, 'sendCode'])->name('worker.proof.code.send');
    Route::post('code/check', [ProofController::class, 'checkCode'])->name('worker.proof.code.check');
});

// The installable app: its manifest, with no session and no cookie (with SESSION_DRIVER=database each request without a
// cookie would open a row in `sessions`, and an installed phone asks for these often); nothing of anyone is in it.
Route::withoutMiddleware([
    EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class,
    PreventRequestForgery::class, EndRevokedImpersonation::class, AssignRequestContext::class, ForbidWhileImpersonating::class,
    HandleAppearance::class, HandleInertiaRequests::class,
])->middleware(['throttle:worker-enter', ResolveWorkerTenant::class])->group(function (): void {
    Route::get('me/{tenant:slug}/manifest.webmanifest', [InstallableAppController::class, 'manifest'])->name('worker.app.manifest');
});
