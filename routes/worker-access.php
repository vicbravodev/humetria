<?php

declare(strict_types=1);

// Required from routes/web.php: the pages of the person who works (no account), under me/{tenant:slug}. Every name starts
// with worker.

use App\Modules\WorkerAccess\Http\Controllers\AccessLinkController;
use App\Modules\WorkerAccess\Http\Controllers\EntryController;
use App\Modules\WorkerAccess\Http\Controllers\HomeController;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Support\Facades\Route;

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
