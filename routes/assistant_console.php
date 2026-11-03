<?php

declare(strict_types=1);

use App\Modules\Assistant\Http\Controllers\TenantSettingsController;
use Illuminate\Support\Facades\Route;

// What the superadmin decides about Humi for a company, next to the other actions on a company in routes/tenancy.php.
Route::get('admin/companies/{tenant}/assistant', [TenantSettingsController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('tenant')
    ->name('companies.assistant.show');

Route::put('admin/companies/{tenant}/assistant', [TenantSettingsController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('tenant')
    ->name('companies.assistant.update');
