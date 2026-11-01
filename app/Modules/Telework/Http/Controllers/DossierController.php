<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Telework\Support\CommitteeGate;
use App\Modules\Telework\Support\Dossier;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The printable dossier of one work center for a STPS inspection. Owner, hr and the Comisión assigned to the center
 * read it; the viewer, another center's Comisión, another company and a made-up center are all a 404. Every view is
 * audited and logged, with the center's id only.
 */
final class DossierController extends Controller
{
    public function show(Request $request, mixed $tenant, string $workCenter, WorkCenters $workCenters, CommitteeGate $gate, Dossier $dossier, RecordAudit $audit): Response
    {
        Gate::authorize('view-telework');
        $user = $request->user();
        $profile = Str::isUuid($workCenter) && $workCenters->findActive($workCenter) !== null ? $workCenters->profile($workCenter) : null;
        $manage = Gate::allows('manage-telework');
        abort_if($user === null || $profile === null || ! ($manage || $gate->isMember($user->id, $profile->id)), 404);

        $record = ['work_center_id' => $profile->id];
        $audit->handle('telework.dossier_viewed', null, $record);
        Log::info('telework.dossier_viewed', $record);

        return Inertia::render('telework/dossier', $dossier->build($tenant, $profile, $user->name, $manage));
    }
}
