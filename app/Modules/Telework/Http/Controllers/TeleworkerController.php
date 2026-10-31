<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Telework\Actions\AddWorkplace;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\RegisterAspirant;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Http\Requests\RegisterAspirantRequest;
use App\Modules\Telework\Http\Requests\WorkplaceRequest;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RH registers the people who will telework from a work center, adds their workplaces and gives them their portal link.
 * Only whoever manages Telework; a person, work center or period outside the company is a 404.
 */
final class TeleworkerController extends Controller
{
    public function __construct(private readonly WorkCenters $workCenters, private readonly Roster $roster) {}

    public function create(mixed $tenant, string $workCenter): Response
    {
        Gate::authorize('manage-telework');

        $center = $this->center($workCenter);

        return $this->page($center->id, $center->name, null);
    }

    /**
     * The active people of the center whose name, email or number has what was typed, for the picker.
     *
     * @return list<array{value: string, label: string}>
     */
    public function employees(Request $request, mixed $tenant, string $workCenter): array
    {
        Gate::authorize('manage-telework');
        $center = $this->center($workCenter);
        $query = new RosterQuery(activeInWorkCenter: $center->id, search: $request->string('q')->trim()->limit(100, '')->toString());

        return array_values(array_map(
            fn (RosterEntry $person): array => ['value' => $person->id, 'label' => $person->name],
            $this->roster->page($query, perPage: 20)->items(),
        ));
    }

    public function store(RegisterAspirantRequest $request, mixed $tenant, string $workCenter, RegisterAspirant $register): RedirectResponse
    {
        $this->center($workCenter);
        abort_if($request->user() === null, 403);

        $teleworker = $register->handle($request->employeeId(), $request->percentage(), $request->addresses(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona registrada. Su lista de verificación ya está pedida.']);

        return redirect()->route('tenant.telework.teleworkers.show', [$tenant, $teleworker->id]);
    }

    public function show(mixed $tenant, string $teleworker): Response
    {
        Gate::authorize('manage-telework');
        $period = $this->teleworker($teleworker);
        // The period keeps its center even if it was archived since.
        $center = $this->workCenters->profile($period->work_center_id);
        abort_if($center === null, 404);

        return $this->page($center->id, $center->name, $period);
    }

    /** A new link, shown once: only its hash is kept, and the one the person had stops working. */
    public function link(mixed $tenant, string $teleworker, IssuePortalLink $issue): RedirectResponse
    {
        Gate::authorize('manage-telework');
        $period = $this->teleworker($teleworker);

        Inertia::flash('portalLink', route('portal.show', $issue->handle($period->employee_id)));

        return redirect()->route('tenant.telework.teleworkers.show', [$tenant, $period->id]);
    }

    public function addWorkplace(WorkplaceRequest $request, mixed $tenant, string $teleworker, AddWorkplace $add): RedirectResponse
    {
        $period = $this->teleworker($teleworker);
        abort_if($period->status === TeleworkerStatus::Ended, 404);

        $add->handle($period, $request->address());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lugar agregado. Su lista de verificación ya está pedida.']);

        return redirect()->route('tenant.telework.teleworkers.show', [$tenant, $period->id]);
    }

    private function page(string $workCenterId, string $workCenterName, ?Teleworker $teleworker): Response
    {
        return Inertia::render('telework/teleworker', [
            'workCenter' => ['id' => $workCenterId, 'name' => $workCenterName],
            'hasChecklist' => Checklist::latestPublished($workCenterId) !== null,
            'states' => $this->workCenters->states(),
            'teleworker' => $teleworker === null ? null : $this->summary($teleworker),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Teleworker $teleworker): array
    {
        $person = $this->roster->find($teleworker->employee_id);
        $states = $this->workCenters->states();

        return [
            'id' => $teleworker->id,
            'name' => $person->name ?? '',
            'jobTitle' => $person->jobTitle ?? '',
            'hasEmail' => $person?->email !== null,
            'status' => $teleworker->status->value,
            'percentage' => $teleworker->telework_percentage,
            'workplaces' => $teleworker->workplaces()->with('applications')->get()->map(fn (Workplace $workplace): array => [
                'id' => $workplace->id,
                'address' => "{$workplace->street} {$workplace->exterior_number}, {$workplace->neighborhood}, {$workplace->municipality}, ".($states[$workplace->state] ?? $workplace->state),
                'label' => $this->label($workplace),
            ])->all(),
        ];
    }

    /** What RH sees of the workplace's first checklist; the Comisión's decision comes with its own screen. */
    private function label(Workplace $workplace): string
    {
        $one = $workplace->applications->where('section', Section::One);

        return match (true) {
            $one->contains(fn ($application): bool => $application->submitted_at === null) => 'Pendiente de contestar',
            $one->isNotEmpty() => 'En revisión de la Comisión',
            default => 'Sin lista',
        };
    }

    private function teleworker(string $id): Teleworker
    {
        $teleworker = Str::isUuid($id) ? Teleworker::query()->find($id) : null;
        abort_if($teleworker === null, 404);

        return $teleworker;
    }

    private function center(string $id): WorkCenterSummary
    {
        $center = Str::isUuid($id) ? $this->workCenters->findActive($id) : null;
        abort_if($center === null, 404);

        return $center;
    }
}
