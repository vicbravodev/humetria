<?php

declare(strict_types=1);

use App\Modules\Announcements\Http\Controllers\AnnouncementConsoleController;
use Illuminate\Support\Facades\Route;

// What Humetría tells the companies in their panel, for the superadmin alone (the controller and the request check the gate).
Route::middleware(['auth', 'verified'])->prefix('admin/announcements')->name('announcements.')->group(function (): void {
    Route::get('/', [AnnouncementConsoleController::class, 'index'])->name('index');
    Route::post('/', [AnnouncementConsoleController::class, 'store'])->name('store');
    Route::put('{announcement}', [AnnouncementConsoleController::class, 'update'])->whereUuid('announcement')->name('update');
});
