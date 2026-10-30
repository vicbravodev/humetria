<?php

declare(strict_types=1);

use App\Modules\Campaigns\Http\Controllers\CampaignActionController;
use App\Modules\Campaigns\Http\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/campaigns', [CampaignController::class, 'index'])->where('tenant', $notReserved)->name('tenant.campaigns.index');
Route::post('admin/{tenant:slug}/campaigns', [CampaignController::class, 'store'])->where('tenant', $notReserved)->name('tenant.campaigns.store');
Route::get('admin/{tenant:slug}/campaigns/{campaign}', [CampaignController::class, 'show'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.show');
Route::delete('admin/{tenant:slug}/campaigns/{campaign}', [CampaignController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.destroy');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/redraw', [CampaignActionController::class, 'redraw'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.redraw');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/launch', [CampaignActionController::class, 'launch'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.launch');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/resend', [CampaignActionController::class, 'resend'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.resend');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/participants/{participant}/resend', [CampaignActionController::class, 'resendOne'])->where('tenant', $notReserved)->whereUuid(['campaign', 'participant'])->name('tenant.campaigns.participants.resend');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/invite-missing', [CampaignActionController::class, 'inviteMissing'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.invite-missing');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/replacements', [CampaignActionController::class, 'replacements'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.replacements');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/extend', [CampaignActionController::class, 'extend'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.extend');
Route::post('admin/{tenant:slug}/campaigns/{campaign}/close', [CampaignActionController::class, 'close'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.campaigns.close');
