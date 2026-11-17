<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: the owner's page of single sign-on, under /admin/{tenant}/sso.
// Every name starts with tenant.sso. Only the owner (manage-members) of a company whose plan has the module.

use App\Modules\Sso\Http\Controllers\SsoSettingsController;
use Illuminate\Support\Facades\Route;

/** @var string $notReserved */
Route::get('admin/{tenant:slug}/sso', [SsoSettingsController::class, 'show'])->where('tenant', $notReserved)->name('tenant.sso.show');
Route::put('admin/{tenant:slug}/sso', [SsoSettingsController::class, 'update'])->where('tenant', $notReserved)->name('tenant.sso.update');
