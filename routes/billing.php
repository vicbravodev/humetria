<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controllers\AccountCompaniesController;
use App\Modules\Billing\Http\Controllers\AccountConsoleController;
use App\Modules\Billing\Http\Controllers\LinkableCompaniesController;
use Illuminate\Support\Facades\Route;

// Who pays what, for the staff only: support reads it, the superadmin changes it (each controller checks its gate).
Route::get('admin/billing/accounts', [AccountConsoleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('billing.accounts.index');

Route::post('admin/billing/accounts', [AccountConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('billing.accounts.store');

Route::get('admin/billing/accounts/{account}', [AccountConsoleController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('account')
    ->name('billing.accounts.show');

Route::put('admin/billing/accounts/{account}', [AccountConsoleController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('account')
    ->name('billing.accounts.update');

Route::post('admin/billing/accounts/{account}/companies', [AccountCompaniesController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('account')
    ->name('billing.accounts.companies.store');

Route::get('admin/billing/companies/search', LinkableCompaniesController::class)
    ->middleware(['auth', 'verified'])
    ->name('billing.companies.search');
