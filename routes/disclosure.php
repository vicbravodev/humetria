<?php

declare(strict_types=1);

use App\Modules\Disclosure\Http\Controllers\ContentController;
use App\Modules\Disclosure\Http\Controllers\DisclosureController;
use App\Modules\Disclosure\Http\Controllers\DisseminationController;
use App\Modules\Disclosure\Http\Controllers\PageLinkController;
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

// The physical means (poster, bulletin, leaflet, meeting) RH records as evidence of the disclosure (num. 10.2); never changed or deleted.
Route::post('admin/{tenant:slug}/disclosure/dissemination/records', [DisseminationController::class, 'storeRecord'])->where('tenant', $notReserved)->name('tenant.disclosure.dissemination.records.store');

// The permanent link to the page of the workers: a credential, so only whoever manages sees it. Nothing here deletes it.
Route::get('admin/{tenant:slug}/disclosure/page', [PageLinkController::class, 'show'])->where('tenant', $notReserved)->name('tenant.disclosure.page');
Route::post('admin/{tenant:slug}/disclosure/page/link', [PageLinkController::class, 'issue'])->where('tenant', $notReserved)->name('tenant.disclosure.page.issue');
Route::post('admin/{tenant:slug}/disclosure/page/link/rotation', [PageLinkController::class, 'rotate'])->where('tenant', $notReserved)->name('tenant.disclosure.page.rotate');
Route::post('admin/{tenant:slug}/disclosure/page/link/disabled', [PageLinkController::class, 'disable'])->where('tenant', $notReserved)->name('tenant.disclosure.page.disable');
Route::post('admin/{tenant:slug}/disclosure/page/link/enabled', [PageLinkController::class, 'enable'])->where('tenant', $notReserved)->name('tenant.disclosure.page.enable');
Route::put('admin/{tenant:slug}/disclosure/page/link/expiry', [PageLinkController::class, 'expiry'])->where('tenant', $notReserved)->name('tenant.disclosure.page.expiry');

// The blocks of 5.7 the company writes by hand (b, c, d and f): a draft, its publication to centers and nothing deleted once published.
Route::get('admin/{tenant:slug}/disclosure/contents', [ContentController::class, 'show'])->where('tenant', $notReserved)->name('tenant.disclosure.contents');
Route::post('admin/{tenant:slug}/disclosure/contents/{kind}/draft', [ContentController::class, 'start'])->where('tenant', $notReserved)->whereIn('kind', ['violence_measures', 'prevention_measures', 'complaint_mechanism', 'health_effects'])->name('tenant.disclosure.contents.draft.store');
Route::put('admin/{tenant:slug}/disclosure/contents/{kind}/draft/{document}', [ContentController::class, 'save'])->where('tenant', $notReserved)->whereIn('kind', ['violence_measures', 'prevention_measures', 'complaint_mechanism', 'health_effects'])->whereUuid('document')->name('tenant.disclosure.contents.draft.update');
Route::delete('admin/{tenant:slug}/disclosure/contents/{kind}/draft/{document}', [ContentController::class, 'discard'])->where('tenant', $notReserved)->whereIn('kind', ['violence_measures', 'prevention_measures', 'complaint_mechanism', 'health_effects'])->whereUuid('document')->name('tenant.disclosure.contents.draft.destroy');
Route::post('admin/{tenant:slug}/disclosure/contents/{kind}/draft/{document}/publish', [ContentController::class, 'publish'])->where('tenant', $notReserved)->whereIn('kind', ['violence_measures', 'prevention_measures', 'complaint_mechanism', 'health_effects'])->whereUuid('document')->name('tenant.disclosure.contents.publish');
