<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Engagement\Actions\CloseSurvey;
use App\Modules\Engagement\Actions\LaunchSurvey;
use App\Modules\Engagement\Actions\RemindPending;
use App\Modules\Engagement\Exceptions\SurveyCannotLaunch;
use App\Modules\Engagement\Http\Requests\LaunchSurveyRequest;
use App\Modules\Engagement\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Launch, remind and close a survey: owner and hr; a survey of another company or kind is a 404. */
final readonly class SurveyActionController
{
    public function launch(LaunchSurveyRequest $request, mixed $tenant, string $id, string $kind, LaunchSurvey $launch): RedirectResponse
    {
        $survey = Survey::query()->findOrFail($id);
        abort_unless($survey->kind->value === $kind, 404);

        try {
            $launch->handle($survey->id, $request->closesOn(), $request->user()->id ?? abort(403));
        } catch (SurveyCannotLaunch $refused) {
            throw ValidationException::withMessages(['survey' => $refused->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La encuesta está en curso: las invitaciones salen en unos minutos.']);

        return back();
    }

    public function remind(mixed $tenant, string $id, string $kind, RemindPending $remind): RedirectResponse
    {
        $survey = Survey::query()->findOrFail($id);
        abort_unless($survey->kind->value === $kind, 404);
        Gate::authorize('manage', $survey);

        $queued = $remind->handle($survey->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => $queued === 0 ? 'No hay a quién recordarle: nadie tiene un correo al que enviarle el enlace.' : 'El recordatorio sale en unos minutos.']);

        return back();
    }

    public function close(Request $request, mixed $tenant, string $id, string $kind, CloseSurvey $close): RedirectResponse
    {
        $survey = Survey::query()->findOrFail($id);
        abort_unless($survey->kind->value === $kind, 404);
        Gate::authorize('manage', $survey);

        $close->handle($survey->id, $request->user()->id ?? abort(403));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'La encuesta se cerró: los enlaces ya no funcionan.']);

        return back();
    }
}
