<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\AcceptInvitationController;
use App\Modules\Tenancy\Http\Controllers\AuditConsoleController;
use App\Modules\Tenancy\Http\Controllers\CompanyModulesController;
use App\Modules\Tenancy\Http\Controllers\ImpersonateOwnerController;
use App\Modules\Tenancy\Http\Controllers\InvitationConsoleController;
use App\Modules\Tenancy\Http\Controllers\PlatformOverviewController;
use App\Modules\Tenancy\Http\Controllers\TenantConsoleController;
use App\Modules\Tenancy\Http\Controllers\TenantLogoController;
use App\Modules\Tenancy\Http\Controllers\UserConsoleController;
use Illuminate\Support\Facades\Route;

// The platform in a few figures, for the superadmin.
Route::get('admin/overview', PlatformOverviewController::class)
    ->middleware(['auth', 'verified'])
    ->name('overview.index');

// The platform's audit trail: read only, for the superadmin.
Route::get('admin/audit', [AuditConsoleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('audit.index');

// The platform's people, for the superadmin: help one back in, give or take away support. Never create a superadmin here.
Route::middleware(['auth', 'verified'])->prefix('admin/platform-users')->name('platform-users.')->group(function (): void {
    Route::get('/', [UserConsoleController::class, 'index'])->name('index');
    Route::post('{user}/password-reset', [UserConsoleController::class, 'sendReset'])->name('password-reset');
    Route::delete('{user}/two-factor', [UserConsoleController::class, 'removeTwoFactor'])->name('two-factor.destroy');
    Route::post('{user}/support', [UserConsoleController::class, 'grantSupport'])->name('support.store');
    Route::delete('{user}/support', [UserConsoleController::class, 'revokeSupport'])->name('support.destroy');
});

Route::get('admin/companies', [TenantConsoleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('companies.index');

Route::post('admin/companies', [TenantConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('companies.store');

// One company in the console with its modules: the staff reads them, the superadmin changes them.
Route::get('admin/companies/{tenant}', [CompanyModulesController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('companies.show');

Route::post('admin/companies/{tenant}/modules/grant', [CompanyModulesController::class, 'grant'])
    ->middleware(['auth', 'verified'])
    ->name('companies.modules.grant');

Route::post('admin/companies/{tenant}/modules/revoke', [CompanyModulesController::class, 'revoke'])
    ->middleware(['auth', 'verified'])
    ->name('companies.modules.revoke');

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

Route::post('admin/companies/{tenant}/restriction', [TenantConsoleController::class, 'restrict'])
    ->middleware(['auth', 'verified'])
    ->name('companies.restrict');

Route::post('admin/companies/{tenant}/reactivate', [TenantConsoleController::class, 'reactivate'])
    ->middleware(['auth', 'verified'])
    ->name('companies.reactivate');

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
