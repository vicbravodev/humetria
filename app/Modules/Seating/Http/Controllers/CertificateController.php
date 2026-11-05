<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The printable certificate that Ley Silla does not apply to a work center (rediseño v2, decisiones A2 y D6): only where it
 * declared that nobody works standing and no standing position has been registered since. A center the person may not see,
 * of another company or made up is a 404.
 */
final class CertificateController extends Controller
{
    public function show(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, WorkCenters $workCenters): Response
    {
        Gate::authorize('view-seating');
        $center = $access->find($request, $workCenter);
        $declared = SeatingProfile::query()->where('work_center_id', $center->id)->where('no_standing_positions', true)->first(['declared_by', 'declared_on']);
        $positions = StandingPosition::query()->where('work_center_id', $center->id)->exists();
        $profile = $workCenters->profile($center->id);
        abort_if($declared === null || $positions || $profile === null, 404);

        return Inertia::render('seating/certificate', [
            'workCenter' => ['id' => $profile->id, 'name' => $profile->name, 'legalName' => $profile->legalName, 'rfc' => $profile->rfc, 'address' => $profile->address],
            'declaredOn' => $declared->declared_on->isoFormat('D [de] MMMM [de] YYYY'),
            'declaredBy' => User::query()->whereKey($declared->declared_by)->value('name'),
        ]);
    }
}
