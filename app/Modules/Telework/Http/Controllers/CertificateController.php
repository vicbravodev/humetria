<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Telework\Support\VisibleCenters;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The printable certificate that NOM-037 does not apply to a work center (rediseño v2, decisiones A2 y D6): only where it
 * declared that nobody works away more than 40 % of their time and nobody has been registered since. A center the person
 * may not see, of another company or made up is a 404.
 */
final class CertificateController extends Controller
{
    public function show(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, VisibleCenters $visible): Response
    {
        Gate::authorize('view-telework');
        $user = $request->user();
        abort_if($user === null, 403);
        $center = Str::isUuid($workCenter) ? $workCenters->findActive($workCenter) : null;
        $limit = $visible->limitedTo($user->id);
        abort_if($center === null || ($limit !== null && ! in_array($center->id, $limit, true)), 404);
        $declared = TeleworkProfile::query()->where('work_center_id', $center->id)->where('has_teleworkers', false)->first(['declared_by', 'declared_on']);
        $registered = Teleworker::query()->where('work_center_id', $center->id)->whereIn('status', [TeleworkerStatus::Active, TeleworkerStatus::Aspirant])->exists();
        $profile = $workCenters->profile($center->id);
        abort_if($declared === null || $registered || $profile === null, 404);

        return Inertia::render('telework/certificate', [
            'workCenter' => ['id' => $profile->id, 'name' => $profile->name, 'legalName' => $profile->legalName, 'rfc' => $profile->rfc, 'address' => $profile->address],
            'declaredOn' => $declared->declared_on->isoFormat('D [de] MMMM [de] YYYY'),
            'declaredBy' => User::query()->whereKey($declared->declared_by)->value('name'),
        ]);
    }
}
