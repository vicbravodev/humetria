<?php

declare(strict_types=1);

use App\Modules\Disclosure\Http\Controllers\DisclosureController;
use App\Modules\Disclosure\Http\Controllers\DisseminationController;
use App\Modules\Disclosure\Http\Controllers\PolicyController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}. The panel of the policy
// of prevention and its disclosure (NOM-035 5.1, 5.7 and 7.8). A published version is evidence: no route deletes it (num. 10.4).
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/disclosure', [DisclosureController::class, 'index'])->where('tenant', $notReserved)->name('tenant.disclosure.index');
Route::get('admin/{tenant:slug}/disclosure/policy', [PolicyController::class, 'show'])->where('tenant', $notReserved)->name('tenant.disclosure.policy');
Route::post('admin/{tenant:slug}/disclosure/policy/draft', [PolicyController::class, 'start'])->where('tenant', $notReserved)->name('tenant.disclosure.policy.draft.store');
Route::put('admin/{tenant:slug}/disclosure/policy/draft/{document}', [PolicyController::class, 'save'])->where('tenant', $notReserved)->whereUuid('document')->name('tenant.disclosure.policy.draft.update');
Route::delete('admin/{tenant:slug}/disclosure/policy/draft/{document}', [PolicyController::class, 'discard'])->where('tenant', $notReserved)->whereUuid('document')->name('tenant.disclosure.policy.draft.destroy');
Route::post('admin/{tenant:slug}/disclosure/policy/draft/{document}/publish', [PolicyController::class, 'publish'])->where('tenant', $notReserved)->whereUuid('document')->name('tenant.disclosure.policy.publish');
Route::get('admin/{tenant:slug}/disclosure/policy/versions/{document}', [PolicyController::class, 'version'])->where('tenant', $notReserved)->whereUuid('document')->name('tenant.disclosure.policy.version');

// The follow-up of the acknowledgments: who confirmed the policy, the paper ones RH records and the reminder to the pending.
Route::get('admin/{tenant:slug}/disclosure/dissemination', [DisseminationController::class, 'index'])->where('tenant', $notReserved)->name('tenant.disclosure.dissemination');
Route::post('admin/{tenant:slug}/disclosure/dissemination/{document}/acknowledgments', [DisseminationController::class, 'record'])->where('tenant', $notReserved)->whereUuid('document')->name('tenant.disclosure.dissemination.acknowledgments.store');
Route::post('admin/{tenant:slug}/disclosure/dissemination/{document}/reminders', [DisseminationController::class, 'remind'])->where('tenant', $notReserved)->whereUuid('document')->name('tenant.disclosure.dissemination.reminders.store');
