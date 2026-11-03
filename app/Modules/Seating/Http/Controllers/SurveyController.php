<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaigns\Contracts\CenterSurveys;
use App\Modules\Seating\Http\Requests\LaunchSurveyRequest;
use App\Modules\Seating\Support\CenterAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The confidential survey of a center's seats and rests: RH launches it for everyone in the center through Campaigns
 * (no sample), and sees its results with the minimum of people per group. Only manage-seating; the viewer and the Comisión
 * do not see it.
 */
final class SurveyController extends Controller
{
    public function launch(LaunchSurveyRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, CenterSurveys $surveys): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $surveys->launch($center->id, $request->closesOn(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Encuesta lanzada. Las personas con correo ya van a recibir su enlace.']);

        return redirect()->route('tenant.seating.survey', [$tenant, $center->id]);
    }

    public function close(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, CenterSurveys $surveys): RedirectResponse
    {
        Gate::authorize('manage-seating');
        $center = $access->find($request, $workCenter);
        $surveys->close($center->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Encuesta cerrada. Ya puedes ver sus resultados.']);

        return redirect()->route('tenant.seating.survey', [$tenant, $center->id]);
    }
}
