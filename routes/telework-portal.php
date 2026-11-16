<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Telework\Http\Controllers\PortalChecklistController;
use App\Modules\Telework\Http\Controllers\PortalController;
use App\Modules\Telework\Http\Controllers\PortalDocumentController;
use App\Modules\Telework\Http\Controllers\PortalEquipmentController;
use App\Modules\Telework\Http\Controllers\PortalEvidenceController;
use App\Modules\Telework\Http\Controllers\PortalNoticeController;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use Illuminate\Support\Facades\Route;

// The telework section of the person (NOM-037), in their pages: the company of the slug (a company without NOM-037 answers
// 404), then their session (a visitor goes to enter, and comes back here). Throttled per session and per address; sending is throttled harder.
// Confirming a visit, a document or the equipment takes a recent proof that it is the person (worker.proof).
Route::middleware([ResolveWorkerTenant::class, RequirePortalTool::class.':nom037', RequireWorkerSession::class])->prefix('me/{tenant:slug}/telework')->group(function (): void {
    // Their pending items and what they sent: checklists, photos and visits the Comisión asked for, documents, equipment.
    Route::get('/', [PortalController::class, 'section'])->middleware('throttle:portal-views')->name('portal.telework.show');

    // Answering a checklist: read it, send it once.
    Route::get('checklists/{application}', [PortalChecklistController::class, 'show'])->whereUuid('application')->middleware('throttle:portal-views')->name('portal.checklist.show');
    Route::post('checklists/{application}', [PortalChecklistController::class, 'store'])->whereUuid('application')->middleware('throttle:portal-actions')->name('portal.checklist.store');

    // What the Comisión asked of the person: photos of the workplace and the authorization of a visit.
    Route::post('evidence/{evidenceRequest}/photos', [PortalEvidenceController::class, 'photos'])->whereUuid('evidenceRequest')->middleware('throttle:portal-actions')->name('portal.evidence.store');
    Route::post('visits/{authorization}/confirm', [PortalEvidenceController::class, 'confirmVisit'])->whereUuid('authorization')->middleware(['throttle:portal-actions', 'worker.proof'])->name('portal.visits.confirm');

    // The documents the person was asked to confirm: read the PDF (or the text written with questions) and confirm it once.
    Route::get('documents/{document}/file', [PortalDocumentController::class, 'file'])->whereUuid('document')->middleware('throttle:portal-views')->name('portal.documents.file');
    Route::post('documents/{document}/acknowledge', [PortalDocumentController::class, 'acknowledge'])->whereUuid('document')->middleware(['throttle:portal-actions', 'worker.proof'])->name('portal.documents.acknowledge');

    // The equipment delivered to the person: they confirm they received each item, or refuse it with the reason, once.
    Route::post('equipment/{item}/respond', [PortalEquipmentController::class, 'respond'])->whereUuid('item')->middleware(['throttle:portal-actions', 'worker.proof'])->name('portal.equipment.respond');

    // The person's three written notices (6.3, 6.7, 6.9): the forms and what they sent. Only someone with an open telework period.
    Route::get('notices', [PortalNoticeController::class, 'show'])->middleware('throttle:portal-views')->name('portal.notices.show');
    Route::post('notices', [PortalNoticeController::class, 'store'])->middleware('throttle:portal-actions')->name('portal.notices.store');
});
