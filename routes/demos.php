<?php

declare(strict_types=1);

use App\Modules\Demos\Http\Controllers\DemoRequestConsoleController;
use App\Modules\Demos\Http\Controllers\DemoRequestController;
use App\Modules\Demos\Http\Controllers\DemoRoomController;
use Illuminate\Support\Facades\Route;

Route::post('demo', [DemoRequestController::class, 'store'])
    ->middleware('throttle:demo-requests')
    ->name('demo-requests.store');

Route::get('admin/demo-requests', [DemoRequestConsoleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('demo-requests.index');

Route::put('admin/demo-requests/{demoRequest}', [DemoRequestConsoleController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('demo-requests.update');

// La sala de demostración: the staff prepares the demo company and shares it live.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('admin/demo', [DemoRoomController::class, 'show'])->name('demo-room.show');
    Route::post('admin/demo/prepare', [DemoRoomController::class, 'prepare'])->name('demo-room.prepare');
    Route::post('admin/demo/reset', [DemoRoomController::class, 'reset'])->name('demo-room.reset');
    Route::post('admin/demo/door', [DemoRoomController::class, 'openDoor'])->name('demo-room.door.open');
    Route::delete('admin/demo/door', [DemoRoomController::class, 'closeDoor'])->name('demo-room.door.close');
    Route::post('admin/demo/kiosk', [DemoRoomController::class, 'reissueKiosk'])->name('demo-room.kiosk.reissue');
});
