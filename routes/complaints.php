<?php

declare(strict_types=1);

use App\Modules\Complaints\Http\Controllers\ChannelController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/complaints.
// Every name starts with tenant.complaints.: it is how the restricted roles (LimitRestrictedRoles) are let in. A route to a
// case is found only among the cases assigned to the person (a 404 otherwise); nothing here deletes a complaint.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/complaints/channel', [ChannelController::class, 'show'])->where('tenant', $notReserved)->name('tenant.complaints.channel.show');
Route::put('admin/{tenant:slug}/complaints/channel', [ChannelController::class, 'update'])->where('tenant', $notReserved)->name('tenant.complaints.channel.update');
Route::post('admin/{tenant:slug}/complaints/channel/activate', [ChannelController::class, 'activate'])->where('tenant', $notReserved)->name('tenant.complaints.channel.activate');
Route::post('admin/{tenant:slug}/complaints/channel/pause', [ChannelController::class, 'pause'])->where('tenant', $notReserved)->name('tenant.complaints.channel.pause');
Route::post('admin/{tenant:slug}/complaints/channel/resume', [ChannelController::class, 'resume'])->where('tenant', $notReserved)->name('tenant.complaints.channel.resume');
Route::post('admin/{tenant:slug}/complaints/channel/rotate-code', [ChannelController::class, 'rotate'])->where('tenant', $notReserved)->name('tenant.complaints.channel.rotate');
