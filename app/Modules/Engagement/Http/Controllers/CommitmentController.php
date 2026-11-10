<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Engagement\Actions\SaveCommitment;
use App\Modules\Engagement\Actions\SetCommitmentStatus;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Http\Requests\CommitmentListRequest;
use App\Modules\Engagement\Http\Requests\CommitmentRequest;
use App\Modules\Engagement\Http\Requests\CommitmentStatusRequest;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\CommitmentPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The commitments of the company after a closed survey of the company in the URL. Everyone who views climate reads them;
 * who manages it writes them. Another company's survey or commitment is a 404, the same as one that does not exist.
 */
final class CommitmentController extends Controller
{
    public function index(CommitmentListRequest $request, mixed $tenant, string $survey, SurveyKind $kind, CommitmentPages $pages): Response
    {
        $survey = $this->survey($survey, $kind);
        Gate::authorize('view', $survey);

        return Inertia::render('engagement/commitments', $pages->props($survey, $request->status(), Gate::allows('manage', $survey)));
    }

    public function store(CommitmentRequest $request, mixed $tenant, string $survey, SurveyKind $kind, SaveCommitment $save): RedirectResponse
    {
        $survey = $this->survey($survey, $kind);
        Gate::authorize('manage', $survey);
        $save->handle($request->commitment($survey->id, $request->user()->id ?? abort(403)));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compromiso guardado.']);

        return to_route('tenant.climate.commitments.index', [$tenant, $survey->id]);
    }

    public function update(CommitmentRequest $request, mixed $tenant, string $survey, string $commitment, SurveyKind $kind, SaveCommitment $save): RedirectResponse
    {
        $survey = $this->survey($survey, $kind);
        Gate::authorize('manage', $survey);
        $save->handle($request->commitment($survey->id, $request->user()->id ?? abort(403)), $this->commitment($survey, $commitment)->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compromiso actualizado.']);

        return to_route('tenant.climate.commitments.index', [$tenant, $survey->id]);
    }

    public function status(CommitmentStatusRequest $request, mixed $tenant, string $survey, string $commitment, SurveyKind $kind, SetCommitmentStatus $set): RedirectResponse
    {
        $survey = $this->survey($survey, $kind);
        Gate::authorize('manage', $survey);
        $set->handle($this->commitment($survey, $commitment)->id, $request->status());

        return to_route('tenant.climate.commitments.index', [$tenant, $survey->id]);
    }

    private function survey(string $id, SurveyKind $kind): Survey
    {
        $survey = Survey::query()->findOrFail($id);
        abort_unless($survey->kind === $kind, 404);

        return $survey;
    }

    private function commitment(Survey $survey, string $id): Commitment
    {
        return Commitment::query()->where('survey_id', $survey->id)->findOrFail($id);
    }
}
