<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Http\Requests\ResultsRequest;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Results\ResultsPresenter;
use App\Modules\Engagement\Support\Results\SurveyResults;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The results of a closed survey of the company in the URL: the whole company and, one at a time, its cuts. Every view
 * is audited with which survey and cut, never what it said.
 */
final class ResultsController extends Controller
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function show(ResultsRequest $request, mixed $tenant, string $survey, SurveyKind $kind, ResultsPresenter $presenter): Response
    {
        // Another company's survey is not found, the same 404 as one that does not exist.
        $survey = Survey::query()->findOrFail($survey);
        abort_unless($survey->kind === $kind, 404);
        Gate::authorize('view', $survey);

        $results = SurveyResults::for($survey);
        $cut = $request->cut();

        if ($results->available()) {
            Log::info('engagement.results_viewed', ['survey_id' => $survey->id, 'cut' => $cut?->value]);
            $this->audit->handle('engagement.results_viewed', $survey, ['cut' => $cut?->value]);
        }

        return Inertia::render('engagement/results', [...$presenter->props($results, $cut), 'kind' => $kind->value]);
    }

    /** Question by question: the same company-level figures and minimums, by theme. */
    public function items(mixed $tenant, string $survey, SurveyKind $kind, ResultsPresenter $presenter): Response
    {
        $survey = Survey::query()->findOrFail($survey);
        abort_unless($survey->kind === $kind, 404);
        Gate::authorize('view', $survey);

        $results = SurveyResults::for($survey);

        if ($results->available()) {
            Log::info('engagement.results_viewed', ['survey_id' => $survey->id, 'cut' => null]);
            $this->audit->handle('engagement.results_viewed', $survey, ['cut' => null]);
        }

        return Inertia::render('engagement/items', [...$presenter->itemsProps($results), 'kind' => $kind->value]);
    }
}
