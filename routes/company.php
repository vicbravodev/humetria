<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\MemberController;
use App\Modules\Tenancy\Http\Controllers\OnboardingController;
use App\Modules\Tenancy\Http\Controllers\RouteController;
use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Http\Controllers\WorkCenterApplicabilityController;
use App\Modules\Tenancy\Http\Controllers\WorkCenterController;
use App\Modules\Tenancy\Http\Controllers\WorkCenterOptionsController;
use App\Modules\Tenancy\Http\Middleware\BlockSuspendedTenant;
use App\Modules\Tenancy\Http\Middleware\LimitRestrictedRoles;
use App\Modules\Tenancy\Http\Middleware\LimitUnionToCompensation;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Http\Requests\TenantRules;
use Illuminate\Support\Facades\Route;

// Included last from routes/web.php: admin/{tenant} must not shadow the fixed pages under /admin.
// A reserved word can never be a company, so it never matches: /admin/register stays a plain 404 for guests too.
$notReserved = '(?!(?:'.implode('|', array_map(preg_quote(...), TenantRules::RESERVED_SLUGS)).')$)[^/]+';

Route::middleware(['auth', 'verified', ResolveTenant::class, BlockSuspendedTenant::class, LimitRestrictedRoles::class, LimitUnionToCompensation::class])->group(function () use ($notReserved): void {
    Route::get('admin/{tenant:slug}', TenantDashboardController::class)->where('tenant', $notReserved)->name('tenant.dashboard');

    Route::get('admin/{tenant:slug}/work-centers/options', WorkCenterOptionsController::class)->where('tenant', $notReserved)->name('tenant.work-centers.options');
    Route::get('admin/{tenant:slug}/work-centers', [WorkCenterController::class, 'index'])->where('tenant', $notReserved)->name('tenant.work-centers.index');
    Route::post('admin/{tenant:slug}/work-centers', [WorkCenterController::class, 'store'])->where('tenant', $notReserved)->name('tenant.work-centers.store');
    Route::put('admin/{tenant:slug}/work-centers/{workCenter}', [WorkCenterController::class, 'update'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.update');
    Route::post('admin/{tenant:slug}/work-centers/{workCenter}/archive', [WorkCenterController::class, 'archive'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.archive');
    // «¿Te aplica?» (rediseño v2, decisión A2): what NOM-035 and Ley Silla ask of a center, and the answers of one or more.
    Route::get('admin/{tenant:slug}/work-centers/{workCenter}/applies', [WorkCenterApplicabilityController::class, 'show'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.applies');
    // The route of one standard in one center: each goes to its own page (boards V2-Ruta-N035, V2-Ruta-N037 and V2-Ruta-Silla).
    Route::get('admin/{tenant:slug}/work-centers/{workCenter}/routes/{norm}', [RouteController::class, 'show'])->where('tenant', $notReserved)->whereUuid('workCenter')->whereIn('norm', ['nom035', 'nom037', 'seating'])->name('tenant.work-centers.route');
    // The NOM-035 route of a center (board V2-Ruta-N035, V3-AlDia once done) and «Si hoy llegara una inspección» (V3-Inspeccion),
    // under NOM-035 in the menu: NOM-035 spans several modules, so Tenancy puts it together from their contracts.
    Route::get('admin/{tenant:slug}/campaigns/centers/{workCenter}/route', [RouteController::class, 'nom035'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.nom035.route');
    Route::get('admin/{tenant:slug}/campaigns/centers/{workCenter}/inspection', [RouteController::class, 'inspection'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.nom035.inspection');
    // The first time of a company (board V3-Onboarding): two questions per center, and its routes.
    Route::get('admin/{tenant:slug}/work-centers/onboarding', [OnboardingController::class, 'show'])->where('tenant', $notReserved)->name('tenant.work-centers.onboarding');
    Route::put('admin/{tenant:slug}/work-centers/onboarding', [OnboardingController::class, 'update'])->where('tenant', $notReserved)->name('tenant.work-centers.onboarding.update');
    Route::put('admin/{tenant:slug}/work-centers/applicability', [WorkCenterApplicabilityController::class, 'update'])->where('tenant', $notReserved)->name('tenant.work-centers.applicability.update');
    Route::post('admin/{tenant:slug}/work-centers/{workCenter}/restore', [WorkCenterController::class, 'restore'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.work-centers.restore');

    Route::get('admin/{tenant:slug}/members', [MemberController::class, 'index'])->where('tenant', $notReserved)->name('tenant.members.index');
    Route::get('admin/{tenant:slug}/members/invitations/create', [MemberController::class, 'create'])->where('tenant', $notReserved)->name('tenant.members.invitations.create');
    Route::post('admin/{tenant:slug}/members/invitations', [MemberController::class, 'invite'])->where('tenant', $notReserved)->name('tenant.members.invitations.store');
    Route::post('admin/{tenant:slug}/members/invitations/{invitation}/resend', [MemberController::class, 'resend'])->where('tenant', $notReserved)->whereUuid('invitation')->name('tenant.members.invitations.resend');
    Route::delete('admin/{tenant:slug}/members/invitations/{invitation}', [MemberController::class, 'cancel'])->where('tenant', $notReserved)->whereUuid('invitation')->name('tenant.members.invitations.cancel');
    Route::put('admin/{tenant:slug}/members/{member}', [MemberController::class, 'update'])->where('tenant', $notReserved)->whereNumber('member')->name('tenant.members.update');
    Route::delete('admin/{tenant:slug}/members/{member}', [MemberController::class, 'destroy'])->where('tenant', $notReserved)->whereNumber('member')->name('tenant.members.destroy');

    require __DIR__.'/organization.php';
    require __DIR__.'/campaigns.php';
    require __DIR__.'/telework.php';
    require __DIR__.'/seating.php';
    require __DIR__.'/attendance.php';
    require __DIR__.'/leave.php';
    require __DIR__.'/engagement.php';
    require __DIR__.'/compensation.php';
    require __DIR__.'/complaints.php';
    require __DIR__.'/results.php';
    require __DIR__.'/interventions.php';
    require __DIR__.'/disclosure.php';
    require __DIR__.'/assistant.php';
});
