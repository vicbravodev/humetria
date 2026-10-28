<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\AcceptInvitationController;
use App\Modules\Tenancy\Http\Controllers\InvitationConsoleController;
use App\Modules\Tenancy\Http\Controllers\TenantConsoleController;
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

Route::post('admin/companies/{tenant}/activate', [TenantConsoleController::class, 'activate'])
    ->middleware(['auth', 'verified'])
    ->name('companies.activate');

Route::post('admin/companies/{tenant}/deactivate', [TenantConsoleController::class, 'deactivate'])
    ->middleware(['auth', 'verified'])
    ->name('companies.deactivate');

Route::post('admin/companies/{tenant}/invitations', [InvitationConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('companies.invitations.store');

// Public: whoever was invited has no session yet; the single-use token in the URL is the credential.
Route::get('admin/invitations/{token}', [AcceptInvitationController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('invitations.show');

Route::post('admin/invitations/{token}', [AcceptInvitationController::class, 'accept'])
    ->middleware('throttle:10,1')
    ->name('invitations.accept');
