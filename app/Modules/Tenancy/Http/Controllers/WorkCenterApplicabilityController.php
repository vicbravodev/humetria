<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\RecordApplicability;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Modules\Tenancy\Http\Requests\ApplicabilityRequest;
use App\Modules\Tenancy\Support\ApplicabilityPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «¿Qué te pide cada norma en este centro?» (board V2-Aplica-Otras) and the answers of one or more centers, which each
 * module keeps. An archived center, a made-up one and another company's are a 404.
 */
final class WorkCenterApplicabilityController extends Controller
{
    public function show(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, ApplicabilityPages $pages): Response
    {
        Gate::authorize('manage-seating');

        return Inertia::render('tenancy/applies', $pages->center(self::center($workCenter, $workCenters), $request->query('norm') === 'seating' ? 'seating' : 'nom035'));
    }

    public function update(ApplicabilityRequest $request, mixed $tenant, WorkCenters $workCenters, RecordApplicability $record): RedirectResponse
    {
        $answers = $request->answers();

        foreach ($answers as $answer) {
            self::center($answer->workCenterId, $workCenters);
        }

        $user = $request->user();
        abort_if($user === null, 403);
        $record->handle($answers, $user->id);

        return back();
    }

    private static function center(string $workCenter, WorkCenters $workCenters): WorkCenterSummary
    {
        $center = Str::isUuid($workCenter) ? $workCenters->findActive($workCenter) : null;
        abort_if($center === null, 404);

        return $center;
    }
}
