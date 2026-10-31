<?php

declare(strict_types=1);

use App\Modules\Telework\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

// The personal portal of a teleworker or any employee: no account, the link's token is the credential. Throttled per link
// and per address (TeleworkServiceProvider), so a room can open theirs at once and nobody can try links in bulk.
Route::get('portal/{token}', [PortalController::class, 'show'])->middleware('throttle:portal-views')->name('portal.show');
