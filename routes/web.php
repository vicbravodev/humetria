<?php

declare(strict_types=1);

use App\Http\Controllers\StopImpersonationController;
use App\Modules\Tenancy\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', fn (): Response => Inertia::render('landing'))->name('home');

require __DIR__.'/demos.php';
require __DIR__.'/tenancy.php';
require __DIR__.'/survey.php';
require __DIR__.'/portal.php';
require __DIR__.'/shared-report.php';

Route::prefix('admin')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', HomeController::class)->name('dashboard');
    });

    Route::post('impersonation/stop', StopImpersonationController::class)
        ->middleware('auth')
        ->name('impersonation.stop');

    require __DIR__.'/settings.php';
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

require __DIR__.'/company.php';
