<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\AcceptInvitationController;
use App\Modules\Tenancy\Http\Controllers\ImpersonateOwnerController;
use App\Modules\Tenancy\Http\Controllers\InvitationConsoleController;
use App\Modules\Tenancy\Http\Controllers\TenantConsoleController;
use App\Modules\Tenancy\Http\Controllers\TenantLogoController;
use Illuminate\Support\Facades\Route;

Route::get('admin/companies', [TenantConsoleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('companies.index');

Route::post('admin/companies', [TenantConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('companies.store');

Route::put('admin/companies/{tenant}', [TenantConsoleController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('companies.update');

Route::post('admin/companies/{tenant}/logo', [TenantLogoController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('companies.logo.update');

Route::delete('admin/companies/{tenant}/logo', [TenantLogoController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('companies.logo.destroy');

Route::post('admin/companies/{tenant}/activate', [TenantConsoleController::class, 'activate'])
    ->middleware(['auth', 'verified'])
    ->name('companies.activate');

Route::post('admin/companies/{tenant}/deactivate', [TenantConsoleController::class, 'deactivate'])
    ->middleware(['auth', 'verified'])
    ->name('companies.deactivate');

Route::post('admin/companies/{tenant}/impersonate', ImpersonateOwnerController::class)
    ->middleware(['auth', 'verified'])
    ->name('companies.impersonate');

Route::post('admin/companies/{tenant}/invitations', [InvitationConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('companies.invitations.store');

Route::scopeBindings()->group(function (): void {
    Route::post('admin/companies/{tenant}/invitations/{invitation}/resend', [InvitationConsoleController::class, 'resend'])
        ->middleware(['auth', 'verified'])
        ->name('companies.invitations.resend');

    Route::delete('admin/companies/{tenant}/invitations/{invitation}', [InvitationConsoleController::class, 'cancel'])
        ->middleware(['auth', 'verified'])
        ->name('companies.invitations.cancel');
});

// Public: whoever was invited has no session yet; the single-use token in the URL is the credential.
Route::get('admin/invitations/{token}', [AcceptInvitationController::class, 'show'])
    ->middleware('throttle:invitation-views')
    ->name('invitations.show');

Route::post('admin/invitations/{token}', [AcceptInvitationController::class, 'accept'])
    ->middleware('throttle:invitation-attempts')
    ->name('invitations.accept');

// Signed in as someone else: Fortify's logout would lose the link, so this one comes back to it.
Route::post('admin/invitations/{token}/switch-account', [AcceptInvitationController::class, 'switchAccount'])
    ->middleware('auth')
    ->name('invitations.switch-account');
