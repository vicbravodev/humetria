<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\DeclareApplicability;
use App\Modules\Telework\Http\Requests\ApplicabilityRequest;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «¿Te aplica la NOM-037?» of one work center (board V2-Aplica), for whoever manages NOM-037. An archived center, a made-up
 * one and another company's are a 404.
 */
final class ApplicabilityController extends Controller
{
    public function show(mixed $tenant, string $workCenter, WorkCenters $workCenters): Response
    {
        Gate::authorize('manage-telework');
        $center = $this->center($workCenter, $workCenters);
        $profile = TeleworkProfile::query()->where('work_center_id', $center->id)->first(['has_teleworkers', 'declared_on']);

        return Inertia::render('telework/applies', [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'declaration' => $profile === null ? null : [
                'hasTeleworkers' => $profile->has_teleworkers,
                'declaredOn' => str_replace('.', '', $profile->declared_on->isoFormat('D MMM YYYY')),
            ],
        ]);
    }

    public function update(ApplicabilityRequest $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, DeclareApplicability $declare): RedirectResponse
    {
        $center = $this->center($workCenter, $workCenters);
        $user = $request->user();
        abort_if($user === null, 403);
        $declare->handle($center->id, $request->hasTeleworkers(), $user->id);

        return to_route('tenant.telework.applies.show', [$tenant, $center->id]);
    }

    private function center(string $workCenter, WorkCenters $workCenters): WorkCenterSummary
    {
        $center = Str::isUuid($workCenter) ? $workCenters->findActive($workCenter) : null;
        abort_if($center === null, 404);

        return $center;
    }
}
