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
use App\Modules\Telework\Actions\RetireWorkplace;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Http\Requests\RegisterAspirantRequest;
use App\Modules\Telework\Http\Requests\WorkplaceRequest;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\WorkplaceAddress;
use App\Modules\Telework\Support\WorkplaceStanding;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * RH registers the people who will telework from a work center, adds their workplaces and gives them their portal link.
 * Only whoever manages Telework; a person, work center or period outside the company is a 404.
 */
final class TeleworkerController extends Controller
{
    public function __construct(private readonly WorkCenters $workCenters, private readonly Roster $roster, private readonly WorkplaceAddress $address, private readonly WorkplaceStanding $standing) {}

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

        // The items of a page are already a list.
        return array_values(array_map( // @pest-mutate-ignore: UnwrapArrayValues
            fn (RosterEntry $person): array => ['value' => $person->id, 'label' => $person->name],
            $this->roster->page($query, perPage: 20)->items(),
        ));
    }

    public function store(RegisterAspirantRequest $request, mixed $tenant, string $workCenter, RegisterAspirant $register): RedirectResponse
    {
        // RegisterAspirantRequest::authorize() already answered 404 for a center outside the company, archived or unknown.
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($request->user() === null, 403); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall

        $teleworker = $register->handle($request->employeeId(), $request->percentage(), $request->addresses(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona registrada. Su lista de verificación ya está pedida.']);

        return redirect()->route('tenant.telework.teleworkers.show', [$tenant, $teleworker->id]);
    }

    public function show(mixed $tenant, string $teleworker): Response
    {
        Gate::authorize('manage-telework');
        $period = $this->teleworker($teleworker);
        // The period keeps its center even if it was archived since: profile() finds it, so the check only narrows the type.
        $center = $this->workCenters->profile($period->work_center_id);
        abort_if($center === null, 404); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall

        return $this->page($center->id, $center->name, $period);
    }

    /** A new link, shown once: only its hash is kept, and the one the person had stops working. */
    public function link(mixed $tenant, string $teleworker, IssuePortalLink $issue): RedirectResponse
    {
        Gate::authorize('manage-telework');
        $period = $this->teleworker($teleworker);
        abort_if($period->status === TeleworkerStatus::Ended, 404);

        Inertia::flash('portalLink', route('portal.show', $issue->handle($period->employee_id, revoke: true)));

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

    public function retireWorkplace(mixed $tenant, string $workplace, RetireWorkplace $retire): RedirectResponse
    {
        Gate::authorize('manage-telework');
        $found = Str::isUuid($workplace) ? Workplace::query()->find($workplace) : null;
        abort_if($found === null, 404);

        $retire->handle($found);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lugar retirado. Queda en el historial.']);

        return redirect()->route('tenant.telework.teleworkers.show', [$tenant, $found->teleworker_id]);
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
        // The workplaces are read once, for the roster check and the list.
        $teleworker->load('workplaces.applications');
        $person = $this->roster->find($teleworker->employee_id) ?? throw new LogicException("Teleworker [{$teleworker->id}] has no employee.");
        $zone = config()->string('app.display_timezone');

        return [
            'id' => $teleworker->id,
            'name' => $person->name,
            'jobTitle' => $person->jobTitle,
            'hasEmail' => $person->email !== null,
            'status' => $teleworker->status->value,
            'percentage' => $teleworker->telework_percentage,
            'canJoin' => $teleworker->status === TeleworkerStatus::Aspirant && $this->standing->hasOneInForce($teleworker),
            'today' => CarbonImmutable::now($zone)->toDateString(),
            'startedOn' => $teleworker->started_at?->setTimezone($zone)->toDateString(),
            'noEquipmentReason' => $teleworker->no_equipment_reason,
            'endReason' => $teleworker->end_reason?->label(),
            'endedOn' => $teleworker->ended_at?->setTimezone($zone)->toDateString(),
            'endNote' => $teleworker->end_note,
            'equipment' => $this->equipment($teleworker, $zone),
            'notices' => $this->notices($teleworker, $zone),
            'workplaces' => $teleworker->workplaces->map(fn (Workplace $workplace): array => $this->workplace($workplace, $teleworker->status === TeleworkerStatus::Ended))->all(),
        ];
    }

    /**
     * The items delivered to the person, oldest delivery first, with where each stands.
     *
     * @return list<array<string, mixed>>
     */
    private function equipment(Teleworker $teleworker, string $zone): array
    {
        // A fresh get() is already a list, here and in notices().
        return array_values(EquipmentItem::query() // @pest-mutate-ignore: UnwrapArrayValues
            ->where('teleworker_id', $teleworker->id)->orderBy('delivered_on')->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (EquipmentItem $item): array => [
                'id' => $item->id,
                'kind' => $item->kind->label(),
                'description' => $item->description,
                'deliveredOn' => $item->delivered_on->toDateString(),
                'status' => $item->status->value,
                'statusLabel' => $item->status->label(),
                'refusalReason' => $item->refusal_reason,
                'respondedOn' => $item->responded_at?->setTimezone($zone)->toDateString(),
            ])->all());
    }

    /**
     * The person's written notices, the ones nobody reviewed first.
     *
     * @return list<array<string, mixed>>
     */
    private function notices(Teleworker $teleworker, string $zone): array
    {
        return array_values(WorkerNotice::query() // @pest-mutate-ignore: UnwrapArrayValues
            ->where('teleworker_id', $teleworker->id)->orderByRaw('reviewed_at IS NOT NULL')->orderByDesc('submitted_at')->orderBy('id')->get()
            ->map(fn (WorkerNotice $notice): array => [
                'id' => $notice->id,
                'kind' => $notice->kind->label(),
                'writtenOn' => $notice->written_on->toDateString(),
                'occurredOn' => $notice->occurred_on->toDateString(),
                'description' => $notice->description,
                'status' => $notice->reviewed_at === null ? 'Recibido' : 'Revisado',
                'reviewedOn' => $notice->reviewed_at?->setTimezone($zone)->toDateString(),
            ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function workplace(Workplace $workplace, bool $ended): array
    {
        $status = $this->standing->of($workplace);

        return [
            'id' => $workplace->id,
            'address' => $this->address->line($workplace),
            'label' => $this->label($workplace),
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'expiresOn' => $ended ? null : $this->standing->expiresOn($workplace)?->setTimezone(config()->string('app.display_timezone'))->toDateString(),
        ];
    }

    /** What RH sees of the workplace's first checklist; the Comisión's decision comes with its own screen. */
    private function label(Workplace $workplace): string
    {
        $one = $workplace->applications->where('section', Section::One);

        return match (true) {
            $one->isNotEmpty() && $one->every(fn ($application): bool => $application->cancelled_at !== null) => 'Cancelada',
            $one->contains(fn ($application): bool => $application->submitted_at === null && $application->cancelled_at === null) => 'Pendiente de contestar',
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
