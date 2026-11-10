<?php

declare(strict_types=1);

// Required from routes/web.php: the vacations of a person and of their team in the employee portal, outside the company
// group. Every name starts with portal.vacations.

use App\Modules\Leave\Http\Controllers\PortalVacationsController;
use Illuminate\Support\Facades\Route;

// No account: the link's token is the credential (the same as NOM-037's), and a company that does not use Vacaciones
// answers 404. Throttled per link and per address (TeleworkServiceProvider's portal-views and portal-actions).
Route::get('portal/{token}/vacations', [PortalVacationsController::class, 'show'])->middleware('throttle:portal-views')->name('portal.vacations.show');

// Asking for days: what the dates would be before sending them, sending the request, and cancelling one of their own.
Route::get('portal/{token}/vacations/preview', [PortalVacationsController::class, 'preview'])->middleware('throttle:portal-views')->name('portal.vacations.preview');
Route::post('portal/{token}/vacations/requests', [PortalVacationsController::class, 'store'])->middleware('throttle:portal-actions')->name('portal.vacations.requests.store');
Route::post('portal/{token}/vacations/requests/{request}/cancel', [PortalVacationsController::class, 'cancel'])->whereUuid('request')->middleware('throttle:portal-actions')->name('portal.vacations.requests.cancel');
