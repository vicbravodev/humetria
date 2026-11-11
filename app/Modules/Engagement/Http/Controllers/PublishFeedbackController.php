<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Engagement\Actions\PublishFeedback;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Publishes the commitments of a closed survey of the company in the URL to the people of the company. */
final class PublishFeedbackController extends Controller
{
    public function store(Request $request, mixed $tenant, string $survey, SurveyKind $kind, PublishFeedback $publish): RedirectResponse
    {
        $survey = Survey::query()->findOrFail($survey);
        abort_unless($survey->kind === $kind, 404);
        Gate::authorize('manage', $survey);
        $publish->handle($survey->id, $request->user()->id ?? abort(403));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicado. Las personas recibirán un correo con su enlace.']);

        return to_route("tenant.{$kind->value}.commitments.index", [$tenant, $survey->id]);
    }
}
