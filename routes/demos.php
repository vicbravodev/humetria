<?php

declare(strict_types=1);

use App\Modules\Demos\Http\Controllers\DemoGuideController;
use App\Modules\Demos\Http\Controllers\DemoRequestConsoleController;
use App\Modules\Demos\Http\Controllers\DemoRequestController;
use App\Modules\Demos\Http\Controllers\DemoRoomController;
use App\Modules\Demos\Http\Controllers\DoorController;
use Illuminate\Support\Facades\Route;

Route::post('demo', [DemoRequestController::class, 'store'])
    ->middleware('throttle:demo-requests')
    ->name('demo-requests.store');

Route::get('admin/demo-requests', [DemoRequestConsoleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('demo-requests.index');

Route::get('admin/demo-requests/ad-conversions', [DemoRequestConsoleController::class, 'adConversions'])
    ->middleware(['auth', 'verified'])
    ->name('demo-requests.ad-conversions');

Route::get('admin/demo-requests/ad-conversions/ios', [DemoRequestConsoleController::class, 'adConversionsIos'])
    ->middleware(['auth', 'verified'])
    ->name('demo-requests.ad-conversions-ios');

Route::put('admin/demo-requests/{demoRequest}', [DemoRequestConsoleController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('demo-requests.update');

Route::post('admin/demo-requests/{demoRequest}/schedule', [DemoRequestConsoleController::class, 'schedule'])
    ->middleware(['auth', 'verified'])
    ->name('demo-requests.schedule');

Route::post('admin/demo-requests/{demoRequest}/discard', [DemoRequestConsoleController::class, 'discard'])
    ->middleware(['auth', 'verified'])
    ->name('demo-requests.discard');

// The door of a live demo (its QR): public, the code is the credential of a while the console opened; throttled per address.
Route::get('demo/{code}', DoorController::class)
    ->middleware('throttle:demo-door')
    ->name('demo.door');

// La sala de demostración: the staff prepares the demo company and shares it live.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('admin/demo', [DemoRoomController::class, 'show'])->name('demo-room.show');
    Route::post('admin/demo/prepare', [DemoRoomController::class, 'prepare'])->name('demo-room.prepare');
    Route::post('admin/demo/reset', [DemoRoomController::class, 'reset'])->name('demo-room.reset');
    Route::post('admin/demo/session', [DemoRoomController::class, 'startSession'])->name('demo-room.session.start');
    Route::delete('admin/demo/session', [DemoRoomController::class, 'endSession'])->name('demo-room.session.end');
    Route::post('admin/demo/door', [DemoRoomController::class, 'openDoor'])->name('demo-room.door.open');
    Route::delete('admin/demo/door', [DemoRoomController::class, 'closeDoor'])->name('demo-room.door.close');
    Route::post('admin/demo/kiosk', [DemoRoomController::class, 'reissueKiosk'])->name('demo-room.kiosk.reissue');
    // The guide of the session, polled by its dock from inside the impersonated demo (DemoGuideRequest).
    Route::get('admin/demo/session/guide', [DemoGuideController::class, 'show'])->name('demo-room.session.guide');
    Route::post('admin/demo/session/steps/{step}/skip', [DemoGuideController::class, 'skip'])->where('step', '[a-z0-9]+\.[a-z0-9]+')->name('demo-room.session.skip');
    Route::get('admin/demo/session/people/options', [DemoGuideController::class, 'people'])->name('demo-room.session.people.options');
    Route::post('admin/demo/session/portal/{employee}', [DemoGuideController::class, 'portal'])->whereUuid('employee')->name('demo-room.session.portal');
    Route::post('admin/demo/session/steps/{step}/rewind', [DemoGuideController::class, 'rewind'])->where('step', '[a-z0-9]+\.[a-z0-9]+')->name('demo-room.session.rewind');
});
