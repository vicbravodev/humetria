<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\RecordApplicability;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Http\Requests\ApplicabilityRequest;
use App\Modules\Tenancy\Support\ApplicabilityPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The first time of a company (board V3-Onboarding): once its centers exist, two questions per center create its routes
 * and the certificates of «no aplica». A center archived, made up or of another company is a 404.
 */
final class OnboardingController extends Controller
{
    public function show(ApplicabilityPages $pages): Response
    {
        Gate::authorize('manage-telework');
        Gate::authorize('manage-seating');

        return Inertia::render('tenancy/onboarding', $pages->onboarding());
    }

    public function update(ApplicabilityRequest $request, mixed $tenant, WorkCenters $workCenters, RecordApplicability $record): RedirectResponse
    {
        $answers = $request->answers();

        foreach ($answers as $answer) {
            abort_if($workCenters->findActive($answer->workCenterId) === null, 404);
        }

        $user = $request->user();
        abort_if($user === null, 403);
        $record->handle($answers, $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listo: tus rutas están en el panel y las constancias de lo que no aplica, en cada centro.']);

        return to_route('tenant.dashboard', $tenant);
    }
}
