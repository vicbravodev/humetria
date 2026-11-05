<?php

declare(strict_types=1);

use App\Modules\Disclosure\Http\Controllers\ContentController;
use App\Modules\Disclosure\Http\Controllers\DisclosureController;
use App\Modules\Disclosure\Http\Controllers\DisseminationController;
use App\Modules\Disclosure\Http\Controllers\DossierController;
use App\Modules\Disclosure\Http\Controllers\PageLinkController;
use App\Modules\Disclosure\Http\Controllers\PolicyController;
use App\Modules\Disclosure\Http\Controllers\PosterController;
use App\Modules\Disclosure\Http\Controllers\ResultsController;
use App\Modules\Disclosure\Http\Controllers\ShareResultsController;
use App\Modules\Disclosure\Http\Controllers\WritingController;
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

// The policy and the blocks of 5.7 written with questions (decisión A3): every answer is kept in the draft of its kind, which
// publishes through the routes above and below.
Route::get('admin/{tenant:slug}/disclosure/write/{kind}', [WritingController::class, 'show'])->where('tenant', $notReserved)->name('tenant.disclosure.write');
Route::put('admin/{tenant:slug}/disclosure/write/{kind}', [WritingController::class, 'update'])->where('tenant', $notReserved)->name('tenant.disclosure.write.update');

// The follow-up of the acknowledgments: who confirmed the policy, the paper ones RH records and the reminder to the pending.
Route::get('admin/{tenant:slug}/disclosure/dissemination', [DisseminationController::class, 'index'])->where('tenant', $notReserved)->name('tenant.disclosure.dissemination');
Route::post('admin/{tenant:slug}/disclosure/dissemination/{document}/acknowledgments', [DisseminationController::class, 'record'])->where('tenant', $notReserved)->whereUuid('document')->name('tenant.disclosure.dissemination.acknowledgments.store');
Route::post('admin/{tenant:slug}/disclosure/dissemination/{document}/reminders', [DisseminationController::class, 'remind'])->where('tenant', $notReserved)->whereUuid('document')->name('tenant.disclosure.dissemination.reminders.store');

// The physical means (poster, bulletin, leaflet, meeting) RH records as evidence of the disclosure (num. 10.2); never changed or deleted.
Route::post('admin/{tenant:slug}/disclosure/dissemination/records', [DisseminationController::class, 'storeRecord'])->where('tenant', $notReserved)->name('tenant.disclosure.dissemination.records.store');
Route::get('admin/{tenant:slug}/disclosure/dissemination/records/{record}/evidence', [DisseminationController::class, 'evidence'])->where('tenant', $notReserved)->whereUuid('record')->name('tenant.disclosure.dissemination.records.evidence');

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

// The results of the closed evaluations that RH publishes to the people (num. 7.8): publish and withdraw, never delete.
Route::get('admin/{tenant:slug}/disclosure/results', [ResultsController::class, 'show'])->where('tenant', $notReserved)->name('tenant.disclosure.results');
Route::get('admin/{tenant:slug}/disclosure/results/{campaign}/share', [ShareResultsController::class, 'show'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.disclosure.results.share');
Route::post('admin/{tenant:slug}/disclosure/results/{campaign}', [ResultsController::class, 'publish'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.disclosure.results.publish');
Route::post('admin/{tenant:slug}/disclosure/results/{campaign}/withdrawal', [ResultsController::class, 'withdraw'])->where('tenant', $notReserved)->whereUuid('campaign')->name('tenant.disclosure.results.withdraw');

// What is printed: the poster with the link and its QR, and the dossier of the disclosure of a center for an inspection. Both are audited.
Route::get('admin/{tenant:slug}/disclosure/page/poster', [PosterController::class, 'show'])->where('tenant', $notReserved)->name('tenant.disclosure.page.poster');
Route::get('admin/{tenant:slug}/disclosure/dossier/{workCenter}', [DossierController::class, 'show'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.disclosure.dossier');
