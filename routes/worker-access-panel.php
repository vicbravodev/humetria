<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: «Accesos», the people's keys and links, under
// /admin/{tenant}/access. Every name starts with tenant.worker-access. Not a tool of the catalog: every company has it.

use App\Modules\WorkerAccess\Http\Controllers\AccessController;
use Illuminate\Support\Facades\Route;

/** @var string $notReserved */
Route::get('admin/{tenant:slug}/access', [AccessController::class, 'index'])->where('tenant', $notReserved)->name('tenant.worker-access.index');
// RH invites a person (emailed; shown to RH once only for a person without email), or every person of a center without a
// key (one queued job each), and reissues a person's access (CUJ 7: every key and session stops).
Route::post('admin/{tenant:slug}/access/invite', [AccessController::class, 'invite'])->where('tenant', $notReserved)->name('tenant.worker-access.invite');
Route::post('admin/{tenant:slug}/access/invite-center', [AccessController::class, 'inviteCenter'])->where('tenant', $notReserved)->name('tenant.worker-access.invite-center');
Route::post('admin/{tenant:slug}/access/reissue', [AccessController::class, 'reissue'])->where('tenant', $notReserved)->name('tenant.worker-access.reissue');
Route::post('admin/{tenant:slug}/access/keys/{credential}/revoke', [AccessController::class, 'revoke'])->where('tenant', $notReserved)->whereUuid('credential')->name('tenant.worker-access.revoke');
