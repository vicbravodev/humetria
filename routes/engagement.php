<?php

declare(strict_types=1);

// Required from routes/company.php, inside the company group: the panel of the climate survey lives under
// /admin/{tenant}/climate. Every name starts with tenant.climate.

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Http\Controllers\CommentsController;
use App\Modules\Engagement\Http\Controllers\CommitmentController;
use App\Modules\Engagement\Http\Controllers\ExportController;
use App\Modules\Engagement\Http\Controllers\ManagerResultsController;
use App\Modules\Engagement\Http\Controllers\PublishFeedbackController;
use App\Modules\Engagement\Http\Controllers\PulseDraftController;
use App\Modules\Engagement\Http\Controllers\ResultsController;
use App\Modules\Engagement\Http\Controllers\SurveyActionController;
use App\Modules\Engagement\Http\Controllers\SurveyController;
use Illuminate\Support\Facades\Route;

// The routes load again with every application a test boots, and a function can only be declared once.
if (! function_exists('engagementRoutes')) {
    /**
     * The panel of one kind of survey. The pages and controllers do not know which kind they serve: it travels as the
     * default of the route, so the pulse can reuse them with its own prefix and names.
     */
    function engagementRoutes(SurveyKind $kind, string $notReserved): void
    {
        $prefix = "admin/{tenant:slug}/{$kind->value}";
        $name = "tenant.{$kind->value}";

        Route::get($prefix, [SurveyController::class, 'index'])->where('tenant', $notReserved)->defaults('kind', $kind->value)->name("{$name}.index");
        Route::post($prefix, $kind === SurveyKind::Pulse ? [PulseDraftController::class, 'store'] : [SurveyController::class, 'store'])->where('tenant', $notReserved)->defaults('kind', $kind->value)->name("{$name}.store");
        Route::get("{$prefix}/{survey}", [SurveyController::class, 'show'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', $kind->value)->name("{$name}.show");
        Route::delete("{$prefix}/{survey}", [SurveyController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', $kind->value)->name("{$name}.destroy");
        Route::post("{$prefix}/{survey}/launch", [SurveyActionController::class, 'launch'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', $kind->value)->name("{$name}.launch");
        Route::post("{$prefix}/{survey}/remind", [SurveyActionController::class, 'remind'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', $kind->value)->name("{$name}.remind");
        Route::post("{$prefix}/{survey}/close", [SurveyActionController::class, 'close'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', $kind->value)->name("{$name}.close");
    }
}

/** @var string $notReserved */
engagementRoutes(SurveyKind::Climate, $notReserved);
engagementRoutes(SurveyKind::Pulse, $notReserved);

// The results of a closed survey: the company, or one cut at a time (?cut=work_center|area|tenure|manager).
Route::get('admin/{tenant:slug}/climate/{survey}/results', [ResultsController::class, 'show'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', 'climate')->name('tenant.climate.results');
// The same results question by question.
Route::get('admin/{tenant:slug}/climate/{survey}/results/items', [ResultsController::class, 'items'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', 'climate')->name('tenant.climate.results.items');
// The same figures as a CSV, under the same minimums.
Route::get('admin/{tenant:slug}/climate/{survey}/export', [ExportController::class, 'show'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', 'climate')->name('tenant.climate.export');
// What the company commits to do after a closed survey: everyone who views climate reads it, who manages it writes it.
Route::get('admin/{tenant:slug}/climate/{survey}/commitments', [CommitmentController::class, 'index'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', 'climate')->name('tenant.climate.commitments.index');
Route::post('admin/{tenant:slug}/climate/{survey}/commitments', [CommitmentController::class, 'store'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', 'climate')->name('tenant.climate.commitments.store');
Route::put('admin/{tenant:slug}/climate/{survey}/commitments/{commitment}', [CommitmentController::class, 'update'])->where('tenant', $notReserved)->whereUuid(['survey', 'commitment'])->defaults('kind', 'climate')->name('tenant.climate.commitments.update');
Route::put('admin/{tenant:slug}/climate/{survey}/commitments/{commitment}/status', [CommitmentController::class, 'status'])->where('tenant', $notReserved)->whereUuid(['survey', 'commitment'])->defaults('kind', 'climate')->name('tenant.climate.commitments.status');
// Publishes «Nos dijeron / Haremos» to the portal of the people of the company, once.
Route::post('admin/{tenant:slug}/climate/{survey}/publish', [PublishFeedbackController::class, 'store'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', 'climate')->name('tenant.climate.publish');
// The managers whose group of people is shown (never a ranking), and the feedback of each.
Route::get('admin/{tenant:slug}/climate/{survey}/managers', [ManagerResultsController::class, 'index'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', 'climate')->name('tenant.climate.managers');
Route::get('admin/{tenant:slug}/climate/{survey}/managers/{manager}', [ManagerResultsController::class, 'show'])->where('tenant', $notReserved)->whereUuid('survey')->whereUuid('manager')->defaults('kind', 'climate')->name('tenant.climate.managers.show');
// The comments about the company (?item=open.best|open.improve): the AI summary and, for who manages climate, the originals.
Route::get('admin/{tenant:slug}/climate/{survey}/comments', [CommentsController::class, 'show'])->where('tenant', $notReserved)->whereUuid('survey')->defaults('kind', 'climate')->name('tenant.climate.comments');
// Tries again a summary that failed; one that is ready is never made twice.
Route::post('admin/{tenant:slug}/climate/summaries/{summary}/retry', [CommentsController::class, 'retry'])->where('tenant', $notReserved)->whereUuid('summary')->defaults('kind', 'climate')->name('tenant.climate.summaries.retry');
