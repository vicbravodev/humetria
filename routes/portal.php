<?php

declare(strict_types=1);

use App\Modules\Telework\Http\Controllers\PortalChecklistController;
use App\Modules\Telework\Http\Controllers\PortalController;
use App\Modules\Telework\Http\Controllers\PortalDocumentController;
use App\Modules\Telework\Http\Controllers\PortalEvidenceController;
use Illuminate\Support\Facades\Route;

// The personal portal of a teleworker or any employee: no account, the link's token is the credential. Throttled per link
// and per address (TeleworkServiceProvider), so a room can open theirs at once and nobody can try links in bulk.
Route::get('portal/{token}', [PortalController::class, 'show'])->middleware('throttle:portal-views')->name('portal.show');

// Answering a checklist: read it, send it once. Sending is throttled per link like the survey's answers.
Route::get('portal/{token}/checklists/{application}', [PortalChecklistController::class, 'show'])->whereUuid('application')->middleware('throttle:portal-views')->name('portal.checklist.show');
Route::post('portal/{token}/checklists/{application}', [PortalChecklistController::class, 'store'])->whereUuid('application')->middleware('throttle:portal-actions')->name('portal.checklist.store');

// What the Comisión asked of the person: photos of the workplace and the authorization of a visit. Sending is throttled per link.
Route::post('portal/{token}/evidence/{evidenceRequest}/photos', [PortalEvidenceController::class, 'photos'])->whereUuid('evidenceRequest')->middleware('throttle:portal-actions')->name('portal.evidence.store');
Route::post('portal/{token}/visits/{authorization}/confirm', [PortalEvidenceController::class, 'confirmVisit'])->whereUuid('authorization')->middleware('throttle:portal-actions')->name('portal.visits.confirm');

// The documents the person was asked to confirm: read the PDF and confirm it once. Confirming is throttled per link.
Route::get('portal/{token}/documents/{document}/file', [PortalDocumentController::class, 'file'])->whereUuid('document')->middleware('throttle:portal-views')->name('portal.documents.file');
Route::post('portal/{token}/documents/{document}/acknowledge', [PortalDocumentController::class, 'acknowledge'])->whereUuid('document')->middleware('throttle:portal-actions')->name('portal.documents.acknowledge');
