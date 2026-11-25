<?php

declare(strict_types=1);

use App\Modules\Operations\Http\Controllers\SystemHealthController;
use Illuminate\Support\Facades\Route;

// How the platform itself is running, for the superadmin: read only.
Route::middleware(['auth', 'verified'])->prefix('admin/operations')->name('operations.')->group(function (): void {
    Route::get('health', SystemHealthController::class)->name('health');
});
