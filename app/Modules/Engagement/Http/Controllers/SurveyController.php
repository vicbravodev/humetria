<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Engagement\Actions\CreateSurveyDraft;
use App\Modules\Engagement\Actions\DeleteSurveyDraft;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyPages;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home of a survey kind and each survey in its state. Owner, hr and viewer read; owner and hr create and delete drafts.
 * The kind comes from the route's default; a survey of another company, or of another kind, is a 404.
 */
final readonly class SurveyController
{
    public function index(mixed $tenant, string $kind, SurveyPages $pages): Response
    {
        $of = SurveyKind::from($kind);
        Gate::authorize($of->viewPermission());

        return Inertia::render('engagement/index', $pages->index($of, Gate::allows($of->managePermission())));
    }

    public function store(Request $request, mixed $tenant, string $kind, CreateSurveyDraft $create): RedirectResponse
    {
        $of = SurveyKind::from($kind);
        Gate::authorize($of->managePermission());

        try {
            // A transaction of its own: the refusal of the database leaves nothing half done, nor a poisoned outer transaction.
            $survey = DB::transaction(fn () => $create->handle($of, $request->user()->id ?? abort(403)));
        } catch (UniqueConstraintViolationException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Ya hay una encuesta en preparación o en curso.']);

            return to_route("tenant.{$kind}.index", $request->route('tenant'));
        }

        return to_route("tenant.{$kind}.show", [$request->route('tenant'), $survey]);
    }

    public function show(mixed $tenant, string $id, string $kind, SurveyPages $pages): Response
    {
        $survey = Survey::query()->findOrFail($id);
        abort_unless($survey->kind->value === $kind, 404);
        Gate::authorize('view', $survey);

        return Inertia::render('engagement/show', $pages->show($survey, Gate::allows('manage', $survey)));
    }

    public function destroy(Request $request, mixed $tenant, string $id, string $kind, DeleteSurveyDraft $delete): RedirectResponse
    {
        $survey = Survey::query()->findOrFail($id);
        abort_unless($survey->kind->value === $kind, 404);
        Gate::authorize('manage', $survey);

        $delete->handle($survey->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'El borrador se eliminó.']);

        return to_route("tenant.{$kind}.index", $request->route('tenant'));
    }
}
