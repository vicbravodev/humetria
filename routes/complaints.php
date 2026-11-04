<?php

declare(strict_types=1);

use App\Modules\Complaints\Http\Controllers\CaseActionController;
use App\Modules\Complaints\Http\Controllers\CenterOptionsController;
use App\Modules\Complaints\Http\Controllers\ChannelController;
use App\Modules\Complaints\Http\Controllers\ComplaintController;
use App\Modules\Complaints\Http\Controllers\EvidenceController;
use App\Modules\Complaints\Http\Controllers\HandlerController;
use App\Modules\Complaints\Http\Controllers\PosterController;
use App\Modules\Complaints\Http\Controllers\UnassignedController;
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

// Who handles the complaints: designated for the company or one work center, ended and never deleted.
Route::get('admin/{tenant:slug}/complaints/handlers', [HandlerController::class, 'index'])->where('tenant', $notReserved)->name('tenant.complaints.handlers.index');
Route::post('admin/{tenant:slug}/complaints/handlers', [HandlerController::class, 'store'])->where('tenant', $notReserved)->name('tenant.complaints.handlers.store');
Route::delete('admin/{tenant:slug}/complaints/handlers/{handler}', [HandlerController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid('handler')->name('tenant.complaints.handlers.destroy');

// The complaints nobody holds are assigned again by the same rules; whoever asks learns only how many were.
Route::post('admin/{tenant:slug}/complaints/unassigned/route', [UnassignedController::class, 'route'])->where('tenant', $notReserved)->name('tenant.complaints.unassigned.route');

// The cases a handler works on: found only among those assigned to the person (a 404 otherwise, never a 403).
Route::get('admin/{tenant:slug}/complaints', [ComplaintController::class, 'index'])->where('tenant', $notReserved)->name('tenant.complaints.index');
Route::get('admin/{tenant:slug}/complaints/cases/{complaint}', [ComplaintController::class, 'show'])->where('tenant', $notReserved)->name('tenant.complaints.cases.show');
Route::get('admin/{tenant:slug}/complaints/centers/options', CenterOptionsController::class)->where('tenant', $notReserved)->name('tenant.complaints.centers.options');

// What a handler does with a case they have; each finds the case among the assigned ones and answers 404 otherwise.
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/acknowledge', [CaseActionController::class, 'acknowledge'])->where('tenant', $notReserved)->name('tenant.complaints.cases.acknowledge');
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/review', [CaseActionController::class, 'review'])->where('tenant', $notReserved)->name('tenant.complaints.cases.review');
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/messages', [CaseActionController::class, 'message'])->where('tenant', $notReserved)->name('tenant.complaints.cases.messages.store');
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/notes', [CaseActionController::class, 'note'])->where('tenant', $notReserved)->name('tenant.complaints.cases.notes.store');
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/share', [CaseActionController::class, 'share'])->where('tenant', $notReserved)->name('tenant.complaints.cases.share');
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/recuse', [CaseActionController::class, 'recuse'])->where('tenant', $notReserved)->name('tenant.complaints.cases.recuse');
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/hold', [CaseActionController::class, 'hold'])->where('tenant', $notReserved)->name('tenant.complaints.cases.hold');
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/close', [CaseActionController::class, 'close'])->where('tenant', $notReserved)->name('tenant.complaints.cases.close');
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/reopen', [CaseActionController::class, 'reopen'])->where('tenant', $notReserved)->name('tenant.complaints.cases.reopen');
Route::post('admin/{tenant:slug}/complaints/cases/{complaint}/export', [CaseActionController::class, 'export'])->where('tenant', $notReserved)->name('tenant.complaints.cases.export');

// The printable evidence of the mechanism for an inspection: how the channel is set up, never a case. Each reading is audited.
Route::get('admin/{tenant:slug}/complaints/evidence', [EvidenceController::class, 'show'])->where('tenant', $notReserved)->name('tenant.complaints.evidence');
Route::post('admin/{tenant:slug}/complaints/evidence/export', [EvidenceController::class, 'export'])->where('tenant', $notReserved)->name('tenant.complaints.evidence.export');

// The poster to print and hang, with the QR of the channel.
Route::get('admin/{tenant:slug}/complaints/poster', [PosterController::class, 'show'])->where('tenant', $notReserved)->name('tenant.complaints.poster');
