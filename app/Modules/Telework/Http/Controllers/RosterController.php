<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Actions\EndTelework;
use App\Modules\Telework\Actions\JoinRoster;
use App\Modules\Telework\Data\RosterData;
use App\Modules\Telework\Http\Requests\EndTeleworkRequest;
use App\Modules\Telework\Http\Requests\JoinRosterRequest;
use App\Modules\Telework\Models\Teleworker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * RH moves an aspirant into the roster, and ends a telework period. A period outside the company is a 404.
 */
final class RosterController extends Controller
{
    public function __construct(private readonly Roster $roster) {}

    public function join(JoinRosterRequest $request, mixed $tenant, string $teleworker, JoinRoster $join): RedirectResponse
    {
        $period = $this->teleworker($teleworker);
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($request->user() === null, 403); // @pest-mutate-ignore

        $join->handle($period, new RosterData(
            $request->maritalStatus(),
            $request->string('activities')->toString(),
            // The foreign key guarantees the employee of every period: the ?-> only narrows the type.
            $this->roster->find($period->employee_id)?->jobTitle, // @pest-mutate-ignore: RemoveNullSafeOperator
            $request->string('contact_phone')->toString(),
            $request->home(),
            $request->string('no_equipment_reason')->toString(),
        ), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona en el padrón de teletrabajo.']);

        return redirect()->route('tenant.telework.teleworkers.show', [$tenant, $period->id]);
    }

    public function end(EndTeleworkRequest $request, mixed $tenant, string $teleworker, EndTelework $end): RedirectResponse
    {
        $period = $this->teleworker($teleworker);
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($request->user() === null, 403); // @pest-mutate-ignore

        $end->handle($period, $request->reason(), $request->endedAt(), $request->note(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Teletrabajo terminado. Queda en el historial.']);

        return redirect()->route('tenant.telework.teleworkers.show', [$tenant, $period->id]);
    }

    private function teleworker(string $id): Teleworker
    {
        $teleworker = Str::isUuid($id) ? Teleworker::query()->find($id) : null;
        abort_if($teleworker === null, 404);

        return $teleworker;
    }
}
