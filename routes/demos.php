<?php

declare(strict_types=1);

use App\Modules\Demos\Http\Controllers\DemoRequestController;
use Illuminate\Support\Facades\Route;

Route::post('demo', [DemoRequestController::class, 'store'])
    ->middleware('throttle:demo-requests')
    ->name('demo-requests.store');
