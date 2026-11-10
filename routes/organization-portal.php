<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\PortalTeamController;
use Illuminate\Support\Facades\Route;

// «Mi equipo» in the personal portal: no account, the link's token is the credential (the same as NOM-037's). Throttled
// per link and per address (TeleworkServiceProvider's portal-views).
Route::get('portal/{token}/team', [PortalTeamController::class, 'show'])->middleware('throttle:portal-views')->name('portal.team.show');
