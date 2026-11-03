<?php

declare(strict_types=1);

use App\Modules\Compensation\Http\Controllers\CompensationController;
use App\Modules\Compensation\Http\Middleware\BlockWhileImpersonating;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/compensation.
// Every name starts with tenant.compensation.; all of them answer 404 while a superadmin impersonates the owner.
/** @var string $notReserved */
Route::middleware(BlockWhileImpersonating::class)->group(function () use ($notReserved): void {
    Route::get('admin/{tenant:slug}/compensation', [CompensationController::class, 'index'])->where('tenant', $notReserved)->name('tenant.compensation.index');
});
