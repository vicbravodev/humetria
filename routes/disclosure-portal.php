<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Disclosure\Http\Controllers\PortalPolicyController;
use Illuminate\Support\Facades\Route;

// The policy page of the personal portal: no account, the link's token is the credential (the same as NOM-037's). Throttled
// per link and per address (TeleworkServiceProvider's portal-views and portal-actions).
Route::get('portal/{token}/policy', [PortalPolicyController::class, 'show'])->middleware('throttle:portal-views')->middleware(RequirePortalTool::class.':nom035')->name('portal.policy.show');
Route::post('portal/{token}/policy/acknowledge', [PortalPolicyController::class, 'acknowledge'])->middleware('throttle:portal-actions')->middleware(RequirePortalTool::class.':nom035')->name('portal.policy.acknowledge');
