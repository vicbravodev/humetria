<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Results\Http\Controllers\PortalSupportController;
use Illuminate\Support\Facades\Route;

// The support message of the personal portal (board V4-N035-Canalizacion): no account, the link's token is the credential (the
// same as NOM-037's). Throttled per link and per address (TeleworkServiceProvider's portal-views).
Route::get('portal/{token}/support', [PortalSupportController::class, 'show'])->middleware('throttle:portal-views')->middleware(RequirePortalTool::class.':nom035')->name('portal.support.show');
