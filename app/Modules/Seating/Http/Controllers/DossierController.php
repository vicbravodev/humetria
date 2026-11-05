<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\Dossier;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The printable dossier of one work center for an inspection. Owner, hr and the Comisión of the center read it; the viewer
 * gets a 403 and a center the person cannot see is a 404. Every view goes to the audit trail and the log, with the id of the
 * center only. `?ready=1` keeps only what the center can show (board V3-Inspeccion).
 */
final class DossierController extends Controller
{
    public function show(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, WorkCenters $workCenters, Dossier $dossier, RecordAudit $audit): Response
    {
        Gate::authorize('assess-seating');
        $user = $request->user();
        abort_if($user === null, 403);
        $center = $access->find($request, $workCenter);
        $profile = $workCenters->profile($center->id);
        abort_if($profile === null, 404);

        $record = ['work_center_id' => $center->id];
        $audit->handle('seating.dossier_viewed', null, $record);
        Log::info('seating.dossier_viewed', $record);

        return Inertia::render('seating/dossier', $dossier->build($profile, $user->name, $request->boolean('ready')));
    }
}
