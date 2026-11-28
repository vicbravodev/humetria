<?php

declare(strict_types=1);

use App\Modules\Announcements\Http\Controllers\AnnouncementConsoleController;
use App\Modules\Announcements\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

// What Humetría tells the companies in their panel, for the superadmin alone (the controller and the request check the gate).
Route::middleware(['auth', 'verified'])->prefix('admin/announcements')->name('announcements.')->group(function (): void {
    Route::get('/', [AnnouncementConsoleController::class, 'index'])->name('index');
    Route::post('/', [AnnouncementConsoleController::class, 'store'])->name('store');
    // Every company's panel in read only while it lasts; its portals and surveys go on.
    Route::post('maintenance', [MaintenanceController::class, 'store'])->name('maintenance.start');
    Route::delete('maintenance', [MaintenanceController::class, 'destroy'])->name('maintenance.end');
    Route::put('{announcement}', [AnnouncementConsoleController::class, 'update'])->whereUuid('announcement')->name('update');
});
