<?php

declare(strict_types=1);

use App\Modules\Seating\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

// The Ley Silla page of the personal portal: no account, the link's token is the credential (the same as NOM-037's). Throttled
// per link and per address (TeleworkServiceProvider's portal-views and portal-actions).
Route::get('portal/{token}/seating', [PortalController::class, 'show'])->middleware('throttle:portal-views')->name('portal.seating.show');
Route::post('portal/{token}/seating/inputs', [PortalController::class, 'store'])->middleware('throttle:portal-actions')->name('portal.seating.inputs.store');
