<?php

declare(strict_types=1);

use App\Modules\Telework\Http\Controllers\AcknowledgementController;
use App\Modules\Telework\Http\Controllers\ChecklistController;
use App\Modules\Telework\Http\Controllers\CommitteeController;
use App\Modules\Telework\Http\Controllers\DocumentController;
use App\Modules\Telework\Http\Controllers\EquipmentController;
use App\Modules\Telework\Http\Controllers\EvidenceController;
use App\Modules\Telework\Http\Controllers\PhotoController;
use App\Modules\Telework\Http\Controllers\ReviewController;
use App\Modules\Telework\Http\Controllers\RosterController;
use App\Modules\Telework\Http\Controllers\TeleworkerController;
use App\Modules\Telework\Http\Controllers\TeleworkSettingsController;
use App\Modules\Telework\Http\Controllers\TrainingController;
use App\Modules\Telework\Http\Controllers\VisitController;
use App\Modules\Telework\Http\Controllers\WorkCenterOptionsController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/telework.
// Every name starts with tenant.telework.: it is how the committee role (LimitCommitteeToTelework) is let in.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/telework/settings', [TeleworkSettingsController::class, 'edit'])->where('tenant', $notReserved)->name('tenant.telework.settings.edit');
Route::put('admin/{tenant:slug}/telework/settings', [TeleworkSettingsController::class, 'update'])->where('tenant', $notReserved)->name('tenant.telework.settings.update');

// The Comisión de seguridad e higiene of a work center: who sits on it. Unassigning is not deleting evidence: it only ends a seat.
Route::get('admin/{tenant:slug}/telework/centers/{workCenter}/committee', [CommitteeController::class, 'show'])->where('tenant', $notReserved)->name('tenant.telework.committee.show');
Route::post('admin/{tenant:slug}/telework/centers/{workCenter}/committee', [CommitteeController::class, 'store'])->where('tenant', $notReserved)->name('tenant.telework.committee.store');
Route::post('admin/{tenant:slug}/telework/centers/{workCenter}/committee/members', [CommitteeController::class, 'assign'])->where('tenant', $notReserved)->name('tenant.telework.committee.members.store');
Route::delete('admin/{tenant:slug}/telework/centers/{workCenter}/committee/members/{user}', [CommitteeController::class, 'unassign'])->where('tenant', $notReserved)->where('user', '[0-9]+')->name('tenant.telework.committee.members.destroy');

// The checklist of a work center: read by RH and the Comisión, edited and published only by the Comisión. Only a draft's
// question can be removed (published versions are evidence); a published list never changes, a new draft copies it.
Route::get('admin/{tenant:slug}/telework/centers/{workCenter}/checklist', [ChecklistController::class, 'show'])->where('tenant', $notReserved)->name('tenant.telework.checklist.show');
Route::post('admin/{tenant:slug}/telework/centers/{workCenter}/checklist/questions', [ChecklistController::class, 'store'])->where('tenant', $notReserved)->name('tenant.telework.checklist.questions.store');
Route::put('admin/{tenant:slug}/telework/centers/{workCenter}/checklist/questions/{question}', [ChecklistController::class, 'update'])->where('tenant', $notReserved)->name('tenant.telework.checklist.questions.update');
Route::delete('admin/{tenant:slug}/telework/centers/{workCenter}/checklist/questions/{question}', [ChecklistController::class, 'destroy'])->where('tenant', $notReserved)->name('tenant.telework.checklist.questions.destroy');
Route::post('admin/{tenant:slug}/telework/centers/{workCenter}/checklist/questions/{question}/move', [ChecklistController::class, 'move'])->where('tenant', $notReserved)->name('tenant.telework.checklist.questions.move');
Route::post('admin/{tenant:slug}/telework/centers/{workCenter}/checklist/draft', [ChecklistController::class, 'draft'])->where('tenant', $notReserved)->name('tenant.telework.checklist.draft');
Route::post('admin/{tenant:slug}/telework/centers/{workCenter}/checklist/publish', [ChecklistController::class, 'publish'])->where('tenant', $notReserved)->name('tenant.telework.checklist.publish');

// The people who will telework, registered by RH from their work center. A period is never deleted; the portal link is
// replaced, not removed (the person's old one stops working).
Route::get('admin/{tenant:slug}/telework/centers/{workCenter}/teleworkers/create', [TeleworkerController::class, 'create'])->where('tenant', $notReserved)->name('tenant.telework.teleworkers.create');
Route::get('admin/{tenant:slug}/telework/centers/{workCenter}/teleworkers/employees', [TeleworkerController::class, 'employees'])->where('tenant', $notReserved)->name('tenant.telework.teleworkers.employees');
Route::post('admin/{tenant:slug}/telework/centers/{workCenter}/teleworkers', [TeleworkerController::class, 'store'])->where('tenant', $notReserved)->name('tenant.telework.teleworkers.store');
Route::get('admin/{tenant:slug}/telework/teleworkers/{teleworker}', [TeleworkerController::class, 'show'])->where('tenant', $notReserved)->name('tenant.telework.teleworkers.show');
Route::post('admin/{tenant:slug}/telework/teleworkers/{teleworker}/portal-link', [TeleworkerController::class, 'link'])->where('tenant', $notReserved)->name('tenant.telework.teleworkers.link');
Route::post('admin/{tenant:slug}/telework/teleworkers/{teleworker}/workplaces', [TeleworkerController::class, 'addWorkplace'])->where('tenant', $notReserved)->name('tenant.telework.teleworkers.workplaces.store');
// The equipment delivered to a person: registered by RH, answered by the person in their portal. No edit and no delete: a
// registration is evidence (C-22, C-29); a mistaken one stays and the person refuses it with the reason.
Route::post('admin/{tenant:slug}/telework/teleworkers/{teleworker}/equipment', [EquipmentController::class, 'store'])->where('tenant', $notReserved)->name('tenant.telework.teleworkers.equipment.store');
Route::post('admin/{tenant:slug}/telework/teleworkers/{teleworker}/roster', [RosterController::class, 'join'])->where('tenant', $notReserved)->name('tenant.telework.teleworkers.join');
Route::post('admin/{tenant:slug}/telework/teleworkers/{teleworker}/end', [RosterController::class, 'end'])->where('tenant', $notReserved)->name('tenant.telework.teleworkers.end');

// The Comisión's review of a center's workplaces: only its assigned members. A decision is never edited or deleted;
// a later review replaces it as the current one.
Route::post('admin/{tenant:slug}/telework/applications/{application}/review', [ReviewController::class, 'store'])->where('tenant', $notReserved)->name('tenant.telework.reviews.store');
Route::post('admin/{tenant:slug}/telework/workplaces/{workplace}/remote-check', [ReviewController::class, 'remoteCheck'])->where('tenant', $notReserved)->name('tenant.telework.workplaces.remote-check.store');

// More evidence about a workplace and in-person visits. The person answers from their portal (routes/portal.php).
Route::post('admin/{tenant:slug}/telework/workplaces/{workplace}/evidence-requests', [EvidenceController::class, 'store'])->where('tenant', $notReserved)->name('tenant.telework.evidence.store');
Route::post('admin/{tenant:slug}/telework/workplaces/{workplace}/visit-authorizations', [VisitController::class, 'requestAuthorization'])->where('tenant', $notReserved)->name('tenant.telework.visit-authorizations.store');
Route::post('admin/{tenant:slug}/telework/visit-authorizations/{authorization}/document', [VisitController::class, 'document'])->where('tenant', $notReserved)->name('tenant.telework.visit-authorizations.document.store');
Route::post('admin/{tenant:slug}/telework/workplaces/{workplace}/visit', [VisitController::class, 'record'])->where('tenant', $notReserved)->name('tenant.telework.workplaces.visit.store');

// The inbox and detail of the checklists to review, and the evidence files (photos, signed paper) through an authorized
// route: assigned Comisión members and RH read them, nobody else.
Route::get('admin/{tenant:slug}/telework/centers/{workCenter}/reviews', [ReviewController::class, 'index'])->where('tenant', $notReserved)->name('tenant.telework.reviews.index');
Route::get('admin/{tenant:slug}/telework/applications/{application}/review', [ReviewController::class, 'show'])->where('tenant', $notReserved)->name('tenant.telework.reviews.show');
Route::get('admin/{tenant:slug}/telework/photos/{photo}', [PhotoController::class, 'checklist'])->where('tenant', $notReserved)->name('tenant.telework.photos.show');
Route::get('admin/{tenant:slug}/telework/evidence-photos/{photo}', [PhotoController::class, 'evidence'])->where('tenant', $notReserved)->name('tenant.telework.evidence-photos.show');
Route::get('admin/{tenant:slug}/telework/visit-authorizations/{authorization}/document', [PhotoController::class, 'authorization'])->where('tenant', $notReserved)->name('tenant.telework.visit-authorizations.document.show');

// A visit request nobody gave can be cancelled (marked, never deleted) by RH or the assigned Comisión.
Route::post('admin/{tenant:slug}/telework/visit-authorizations/{authorization}/cancel', [VisitController::class, 'cancel'])->where('tenant', $notReserved)->name('tenant.telework.visit-authorizations.cancel');

// The company's NOM-037 documents (PDF): every upload is a new version per center; nothing is replaced or deleted, so
// there is no PUT or DELETE here. The file goes out through an authorized route, never a public URL.
Route::get('admin/{tenant:slug}/telework/documents', [DocumentController::class, 'index'])->where('tenant', $notReserved)->name('tenant.telework.documents.index');
Route::post('admin/{tenant:slug}/telework/documents', [DocumentController::class, 'store'])->where('tenant', $notReserved)->name('tenant.telework.documents.store');
Route::get('admin/{tenant:slug}/telework/documents/{document}/file', [DocumentController::class, 'file'])->where('tenant', $notReserved)->name('tenant.telework.documents.file');

// Who confirmed a document: the list (RH and the assigned Comisión), the paper acknowledgements RH records in bulk and a
// Comisión member's own confirmation. Nothing is deleted or edited: a confirmation, once given, stays.
Route::get('admin/{tenant:slug}/telework/documents/{document}/acknowledgements', [AcknowledgementController::class, 'index'])->where('tenant', $notReserved)->name('tenant.telework.documents.acknowledgements.index');
Route::post('admin/{tenant:slug}/telework/documents/{document}/acknowledgements', [AcknowledgementController::class, 'store'])->where('tenant', $notReserved)->name('tenant.telework.documents.acknowledgements.store');
Route::post('admin/{tenant:slug}/telework/documents/{document}/acknowledge', [AcknowledgementController::class, 'acknowledge'])->where('tenant', $notReserved)->name('tenant.telework.documents.acknowledge');

// The trainings given (8.1) and when each person of the roster is due for theirs (5.9). A training is evidence: there is no
// PUT or DELETE. Its list of attendance (PDF) goes out through an authorized route, never a public URL.
Route::get('admin/{tenant:slug}/telework/trainings', [TrainingController::class, 'index'])->where('tenant', $notReserved)->name('tenant.telework.trainings.index');
Route::post('admin/{tenant:slug}/telework/trainings', [TrainingController::class, 'store'])->where('tenant', $notReserved)->name('tenant.telework.trainings.store');
Route::get('admin/{tenant:slug}/telework/trainings/{training}/evidence', [TrainingController::class, 'evidence'])->where('tenant', $notReserved)->name('tenant.telework.trainings.evidence');

// The work centers a NOM-037 field may search (the committee only its own); the company-wide options route is closed to it.
Route::get('admin/{tenant:slug}/telework/work-centers/options', WorkCenterOptionsController::class)->where('tenant', $notReserved)->name('tenant.telework.work-centers.options');
