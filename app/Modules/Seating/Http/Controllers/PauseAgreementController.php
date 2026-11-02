<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\SavePauseAgreement;
use App\Modules\Seating\Http\Requests\PauseAgreementRequest;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\SeatingFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The agreements on pauses with the people of a position (criterio C-14). RH records them; a new one is a new row and none
 * is edited or deleted. The evidence leaves only through an authorized route.
 */
final class PauseAgreementController extends Controller
{
    public function store(PauseAgreementRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, SavePauseAgreement $save): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $found = StandingPosition::query()->where('work_center_id', $center->id)->find($request->string('position_id')->toString());

        if ($found === null) {
            throw ValidationException::withMessages(['position_id' => 'Ese puesto no es de este centro de trabajo.']);
        }

        $save->handle($found, $request->integer('pauses_per_shift'), $request->integer('minutes_each'), $request->string('agreed_on')->toString(), $request->howAgreed(), $request->evidenceFile(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acuerdo de pausas registrado.']);

        return redirect()->route('tenant.seating.plan', [$tenant, $found->work_center_id]);
    }

    public function evidence(Request $request, mixed $tenant, string $agreement, CenterAccess $access, SeatingFiles $files): StreamedResponse
    {
        abort_unless(Gate::allows('manage-seating') || Gate::allows('assess-seating'), 403);
        $found = PauseAgreement::query()->find($agreement);
        abort_if($found === null || $found->evidence_path === null, 404);
        $access->find($request, $found->work_center_id);

        $response = $files->stream($found->evidence_path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
