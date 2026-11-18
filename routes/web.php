<?php

declare(strict_types=1);

use App\Http\Analytics;
use App\Http\Controllers\StopImpersonationController;
use App\Http\Controllers\ToolFilmController;
use App\Http\PageSeo;
use App\Modules\Demos\Http\Middleware\RememberDemoAttribution;
use App\Modules\Tenancy\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

// The tags of the head of a public page come from PageSeo, and this file is the only place that calls it.
Route::get('/', function (): Response {
    Analytics::flashLead();

    return Inertia::render('landing', ['seo' => PageSeo::for('home')]);
})->middleware(RememberDemoAttribution::class)->name('home');
// The notice of privacy: fixed text; the controller of the data (legal name and address) comes from the configuration.
Route::get('/privacy-notice', function (): Response {
    $legal = static fn (string $key): ?string => is_string($value = config("mail.legal.{$key}")) && trim($value) !== '' ? trim($value) : null;

    return Inertia::render('privacy-notice', ['legalName' => $legal('name'), 'legalAddress' => $legal('address'), 'seo' => PageSeo::for('privacy-notice')]);
})->name('privacy-notice');
// What Humetría plans to add: fixed text in the page, nothing from the database, nothing from a person.
Route::get('/roadmap', fn (): Response => Inertia::render('roadmap', ['seo' => PageSeo::for('roadmap')]))->name('roadmap');
Route::get('/media/films/{film}', ToolFilmController::class)->name('films.show');

require __DIR__.'/demos.php';
require __DIR__.'/tenancy.php';
require __DIR__.'/sso.php';
require __DIR__.'/billing.php';
require __DIR__.'/assistant_console.php';
require __DIR__.'/survey.php';
require __DIR__.'/telework-portal.php';
require __DIR__.'/seating-portal.php';
require __DIR__.'/organization-portal.php';
require __DIR__.'/attendance-device.php';
require __DIR__.'/worker-access.php';
require __DIR__.'/leave-portal.php';
require __DIR__.'/engagement-survey.php';
require __DIR__.'/engagement-portal.php';
require __DIR__.'/disclosure-portal.php';
require __DIR__.'/results-portal.php';
require __DIR__.'/workers.php';
require __DIR__.'/complaints-public.php';
require __DIR__.'/shared-report.php';
require __DIR__.'/compensation-shared.php';

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
