<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\TenantConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('admin/companies', [TenantConsoleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('companies.index');

Route::post('admin/companies', [TenantConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('companies.store');
