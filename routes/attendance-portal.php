<?php

declare(strict_types=1);

// Required from routes/web.php: «Mi jornada» in the personal portal, outside the company group. Every name starts with
// portal.attendance. No account: the link's token is the credential (the same as NOM-037's), and a company without the
// registro de jornada answers 404. Throttled per link and per address (TeleworkServiceProvider's portal-views and portal-actions).

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Attendance\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('portal/{token}/attendance', [PortalController::class, 'show'])->middleware('throttle:portal-views')->middleware(RequirePortalTool::class.':attendance')->name('portal.attendance.show');
// The person asks for the link of their phone: it is issued to them alone and replaces the one before.
Route::post('portal/{token}/attendance/enroll', [PortalController::class, 'enroll'])->middleware('throttle:portal-actions')->middleware(RequirePortalTool::class.':attendance')->name('portal.attendance.enroll');
