<?php

declare(strict_types=1);

use App\Modules\Seating\Http\Controllers\AcknowledgmentRequestController;
use App\Modules\Seating\Http\Controllers\AssessmentController;
use App\Modules\Seating\Http\Controllers\DocumentController;
use App\Modules\Seating\Http\Controllers\DossierController;
use App\Modules\Seating\Http\Controllers\MeasureController;
use App\Modules\Seating\Http\Controllers\PauseAgreementController;
use App\Modules\Seating\Http\Controllers\PlanController;
use App\Modules\Seating\Http\Controllers\PositionController;
use App\Modules\Seating\Http\Controllers\ReferralController;
use App\Modules\Seating\Http\Controllers\RegulationController;
use App\Modules\Seating\Http\Controllers\SeatController;
use App\Modules\Seating\Http\Controllers\SeatingController;
use App\Modules\Seating\Http\Controllers\SeatReportController;
use App\Modules\Seating\Http\Controllers\SurveyController;
use App\Modules\Seating\Http\Controllers\SurveyResultsController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/seating.
// Every name starts with tenant.seating.: it is how the committee role (LimitCommitteeToTelework) is let in.
// Nothing of Ley Silla is deleted (criterio C-17): the only DELETE routes end an assignment or cancel a measure.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/seating', [SeatingController::class, 'index'])->where('tenant', $notReserved)->name('tenant.seating.index');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}', [SeatingController::class, 'center'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.center');
Route::put('admin/{tenant:slug}/seating/centers/{workCenter}/profile', [SeatingController::class, 'updateProfile'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.profile.update');

// The positions where people work standing (Figura 1) and who holds them. An assignment is ended, not deleted.
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/positions/create', [PositionController::class, 'create'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.positions.create');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/positions', [PositionController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.positions.store');
Route::get('admin/{tenant:slug}/seating/positions/{position}', [PositionController::class, 'show'])->where('tenant', $notReserved)->whereUuid('position')->name('tenant.seating.positions.show');
Route::put('admin/{tenant:slug}/seating/positions/{position}', [PositionController::class, 'update'])->where('tenant', $notReserved)->whereUuid('position')->name('tenant.seating.positions.update');
Route::get('admin/{tenant:slug}/seating/positions/{position}/employees', [PositionController::class, 'employees'])->where('tenant', $notReserved)->whereUuid('position')->name('tenant.seating.positions.employees');
Route::post('admin/{tenant:slug}/seating/positions/{position}/assignments', [PositionController::class, 'assign'])->where('tenant', $notReserved)->whereUuid('position')->name('tenant.seating.positions.assignments.store');
Route::delete('admin/{tenant:slug}/seating/assignments/{assignment}', [PositionController::class, 'unassign'])->where('tenant', $notReserved)->whereUuid('assignment')->name('tenant.seating.assignments.destroy');

// The seat inventory of a center. A seat is registered and edited, never deleted; its photo leaves only through the app.
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/seats', [SeatController::class, 'index'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.seats.index');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/seats', [SeatController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.seats.store');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/seats/positions', [SeatController::class, 'positions'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.seats.positions');
Route::put('admin/{tenant:slug}/seating/seats/{seat}', [SeatController::class, 'update'])->where('tenant', $notReserved)->whereUuid('seat')->name('tenant.seating.seats.update');
Route::get('admin/{tenant:slug}/seating/seats/{seat}/photo', [SeatController::class, 'photo'])->where('tenant', $notReserved)->whereUuid('seat')->name('tenant.seating.seats.photo');

// The individual assessments (Quinta C and D). Signing is one-way: a signed assessment is final; the person is assessed
// again with a new draft.
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/assessments', [AssessmentController::class, 'index'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.assessments.index');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/assessments', [AssessmentController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.assessments.store');
Route::get('admin/{tenant:slug}/seating/assessments/{assessment}', [AssessmentController::class, 'show'])->where('tenant', $notReserved)->whereUuid('assessment')->name('tenant.seating.assessments.show');
Route::put('admin/{tenant:slug}/seating/assessments/{assessment}', [AssessmentController::class, 'update'])->where('tenant', $notReserved)->whereUuid('assessment')->name('tenant.seating.assessments.update');
Route::post('admin/{tenant:slug}/seating/assessments/{assessment}/sign', [AssessmentController::class, 'sign'])->where('tenant', $notReserved)->whereUuid('assessment')->name('tenant.seating.assessments.sign');

// Confirmation of the risks, reports of damaged seats and referrals. Nothing is deleted: a report is resolved, a referral
// is recorded, a confirmation is kept.
Route::post('admin/{tenant:slug}/seating/assessments/{assessment}/acknowledgment-request', [AcknowledgmentRequestController::class, 'resend'])->where('tenant', $notReserved)->whereUuid('assessment')->name('tenant.seating.acknowledgments.resend');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/acknowledgment-requests', [AcknowledgmentRequestController::class, 'batch'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.acknowledgments.batch');
Route::post('admin/{tenant:slug}/seating/assessments/{assessment}/referrals', [ReferralController::class, 'store'])->where('tenant', $notReserved)->whereUuid('assessment')->name('tenant.seating.referrals.store');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/reports', [SeatReportController::class, 'index'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.reports.index');
Route::post('admin/{tenant:slug}/seating/reports/{report}/resolve', [SeatReportController::class, 'resolve'])->where('tenant', $notReserved)->whereUuid('report')->name('tenant.seating.reports.resolve');

// The action plan: measures (proposed by the Comisión, followed up by RH, cancelled and never deleted) and the agreements on
// pauses (a new one is a new row).
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/plan', [PlanController::class, 'index'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.plan');
Route::post('admin/{tenant:slug}/seating/assessments/{assessment}/measures', [MeasureController::class, 'propose'])->where('tenant', $notReserved)->whereUuid('assessment')->name('tenant.seating.measures.propose');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/measures', [MeasureController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.measures.store');
Route::put('admin/{tenant:slug}/seating/measures/{measure}', [MeasureController::class, 'update'])->where('tenant', $notReserved)->whereUuid('measure')->name('tenant.seating.measures.update');
Route::post('admin/{tenant:slug}/seating/measures/{measure}/cancel', [MeasureController::class, 'cancel'])->where('tenant', $notReserved)->whereUuid('measure')->name('tenant.seating.measures.cancel');
Route::get('admin/{tenant:slug}/seating/measures/{measure}/evidence', [MeasureController::class, 'evidence'])->where('tenant', $notReserved)->whereUuid('measure')->name('tenant.seating.measures.evidence');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/pause-agreements', [PauseAgreementController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.pause-agreements.store');
Route::get('admin/{tenant:slug}/seating/pause-agreements/{agreement}/evidence', [PauseAgreementController::class, 'evidence'])->where('tenant', $notReserved)->whereUuid('agreement')->name('tenant.seating.pause-agreements.evidence');

// Documents (PDF only) and the internal regulation: drafted, signed by the comisión mixta, deposited and distributed. The
// system registers these steps; it deposits and sends nothing. Nothing is deleted.
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/documents', [DocumentController::class, 'index'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.documents.index');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/documents', [DocumentController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.documents.store');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/documents/options', [DocumentController::class, 'options'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.documents.options');
Route::get('admin/{tenant:slug}/seating/documents/{document}/file', [DocumentController::class, 'file'])->where('tenant', $notReserved)->whereUuid('document')->name('tenant.seating.documents.file');
Route::post('admin/{tenant:slug}/seating/assessments/{assessment}/minutes', [DocumentController::class, 'record'])->where('tenant', $notReserved)->whereUuid('assessment')->name('tenant.seating.assessments.minutes');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/regulation', [RegulationController::class, 'show'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.regulation.show');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/regulation', [RegulationController::class, 'store'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.regulation.store');
Route::put('admin/{tenant:slug}/seating/centers/{workCenter}/regulation', [RegulationController::class, 'update'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.regulation.update');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/regulation/advance', [RegulationController::class, 'advance'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.regulation.advance');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/regulation/file', [RegulationController::class, 'file'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.regulation.file');

// The confidential survey of a center's seats and rests: launched through Campaigns for everyone in the center, its results
// read with the minimum of people per group.
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/survey', [SurveyController::class, 'launch'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.survey.launch');
Route::post('admin/{tenant:slug}/seating/centers/{workCenter}/survey/close', [SurveyController::class, 'close'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.survey.close');
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/survey', [SurveyResultsController::class, 'show'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.survey');

// The printable dossier of the center for an inspection; every view is audited.
Route::get('admin/{tenant:slug}/seating/centers/{workCenter}/dossier', [DossierController::class, 'show'])->where('tenant', $notReserved)->whereUuid('workCenter')->name('tenant.seating.dossier');
