<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Http\Requests\TenantRules;
use Illuminate\Support\Facades\Route;

// Included last from routes/web.php: admin/{tenant} must not shadow the fixed pages under /admin.
// A reserved word can never be a company, so it never matches: /admin/register stays a plain 404 for guests too.
$notReserved = '(?!(?:'.implode('|', array_map(preg_quote(...), TenantRules::RESERVED_SLUGS)).')$)[^/]+';

Route::middleware(['auth', 'verified', ResolveTenant::class])->group(function () use ($notReserved): void {
    Route::get('admin/{tenant:slug}', TenantDashboardController::class)->where('tenant', $notReserved)->name('tenant.dashboard');
});
