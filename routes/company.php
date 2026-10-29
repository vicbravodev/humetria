<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Http\Controllers\WorkCenterController;
use App\Modules\Tenancy\Http\Middleware\BlockSuspendedTenant;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Http\Requests\TenantRules;
use Illuminate\Support\Facades\Route;

// Included last from routes/web.php: admin/{tenant} must not shadow the fixed pages under /admin.
// A reserved word can never be a company, so it never matches: /admin/register stays a plain 404 for guests too.
$notReserved = '(?!(?:'.implode('|', array_map(preg_quote(...), TenantRules::RESERVED_SLUGS)).')$)[^/]+';

Route::middleware(['auth', 'verified', ResolveTenant::class, BlockSuspendedTenant::class])->group(function () use ($notReserved): void {
    Route::get('admin/{tenant:slug}', TenantDashboardController::class)->where('tenant', $notReserved)->name('tenant.dashboard');

    Route::get('admin/{tenant:slug}/work-centers', [WorkCenterController::class, 'index'])->where('tenant', $notReserved)->name('tenant.work-centers.index');
    Route::post('admin/{tenant:slug}/work-centers', [WorkCenterController::class, 'store'])->where('tenant', $notReserved)->name('tenant.work-centers.store');
    Route::put('admin/{tenant:slug}/work-centers/{workCenter}', [WorkCenterController::class, 'update'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.update');
    Route::post('admin/{tenant:slug}/work-centers/{workCenter}/archive', [WorkCenterController::class, 'archive'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.archive');
    Route::post('admin/{tenant:slug}/work-centers/{workCenter}/restore', [WorkCenterController::class, 'restore'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.restore');
});
