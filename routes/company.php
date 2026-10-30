<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\MemberController;
use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Http\Controllers\WorkCenterController;
use App\Modules\Tenancy\Http\Controllers\WorkCenterOptionsController;
use App\Modules\Tenancy\Http\Middleware\BlockSuspendedTenant;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Http\Requests\TenantRules;
use Illuminate\Support\Facades\Route;

// Included last from routes/web.php: admin/{tenant} must not shadow the fixed pages under /admin.
// A reserved word can never be a company, so it never matches: /admin/register stays a plain 404 for guests too.
$notReserved = '(?!(?:'.implode('|', array_map(preg_quote(...), TenantRules::RESERVED_SLUGS)).')$)[^/]+';

Route::middleware(['auth', 'verified', ResolveTenant::class, BlockSuspendedTenant::class])->group(function () use ($notReserved): void {
    Route::get('admin/{tenant:slug}', TenantDashboardController::class)->where('tenant', $notReserved)->name('tenant.dashboard');

    Route::get('admin/{tenant:slug}/work-centers/options', WorkCenterOptionsController::class)->where('tenant', $notReserved)->name('tenant.work-centers.options');
    Route::get('admin/{tenant:slug}/work-centers', [WorkCenterController::class, 'index'])->where('tenant', $notReserved)->name('tenant.work-centers.index');
    Route::post('admin/{tenant:slug}/work-centers', [WorkCenterController::class, 'store'])->where('tenant', $notReserved)->name('tenant.work-centers.store');
    Route::put('admin/{tenant:slug}/work-centers/{workCenter}', [WorkCenterController::class, 'update'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.update');
    Route::post('admin/{tenant:slug}/work-centers/{workCenter}/archive', [WorkCenterController::class, 'archive'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.archive');
    Route::post('admin/{tenant:slug}/work-centers/{workCenter}/restore', [WorkCenterController::class, 'restore'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.restore');

    Route::get('admin/{tenant:slug}/members', [MemberController::class, 'index'])->where('tenant', $notReserved)->name('tenant.members.index');
    Route::post('admin/{tenant:slug}/members/invitations', [MemberController::class, 'invite'])->where('tenant', $notReserved)->name('tenant.members.invitations.store');
    Route::post('admin/{tenant:slug}/members/invitations/{invitation}/resend', [MemberController::class, 'resend'])->where('tenant', $notReserved)->whereUuid('invitation')->name('tenant.members.invitations.resend');
    Route::delete('admin/{tenant:slug}/members/invitations/{invitation}', [MemberController::class, 'cancel'])->where('tenant', $notReserved)->whereUuid('invitation')->name('tenant.members.invitations.cancel');
    Route::put('admin/{tenant:slug}/members/{member}', [MemberController::class, 'update'])->where('tenant', $notReserved)->whereNumber('member')->name('tenant.members.update');
    Route::delete('admin/{tenant:slug}/members/{member}', [MemberController::class, 'destroy'])->where('tenant', $notReserved)->whereNumber('member')->name('tenant.members.destroy');

    require __DIR__.'/organization.php';
    require __DIR__.'/campaigns.php';
});
