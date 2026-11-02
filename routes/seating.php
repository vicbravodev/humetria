<?php

declare(strict_types=1);

use App\Modules\Seating\Http\Controllers\SeatingController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/seating.
// Every name starts with tenant.seating.: it is how the committee role (LimitCommitteeToTelework) is let in.
// Nothing of Ley Silla is deleted (criterio C-17): the only DELETE routes end an assignment or cancel a measure.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/seating', [SeatingController::class, 'index'])->where('tenant', $notReserved)->name('tenant.seating.index');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}', [SeatingController::class, 'center'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.center');
Route::put('admin/{tenant:slug}/seating/centers/{workCenter}/profile', [SeatingController::class, 'updateProfile'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.profile.update');
