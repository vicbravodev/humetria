<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Actions\CancelRequest;
use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Actions\RequestLeave;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Exceptions\RequestRefused;
use App\Modules\Leave\Http\Requests\DecisionRequest;
use App\Modules\Leave\Http\Requests\RecordLeaveRequest;
use App\Modules\Leave\Support\Balances;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\PeriodBalance;
use App\Modules\Leave\Support\RequestPages;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RH's queue of vacation and leave requests: owner, hr and viewer read it, owner and hr answer, correct and record. A work
 * center that is not one in use of the company is a 404.
 */
final readonly class RequestController
{
    public const int OPTIONS_LIMIT = 20;

    public function __construct(private WorkCenters $centers, private Roster $roster) {}

    public function index(Request $request, LeaveDefaults $defaults, RequestPages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();

        return Inertia::render('leave/requests', $pages->index(
            $request->string('tab')->toString(),
            $request->string('q')->trim()->limit(100, '')->toString(),
            $this->uuid($request, 'center', fn (string $id): bool => $this->centers->findActive($id) !== null),
            $this->uuid($request, 'area', fn (): bool => true),
            Gate::allows('manage-vacations'),
        ));
    }

    /**
     * RH records vacations or a leave for somebody, even days already gone, and may answer it in the same step. Nobody is
     * emailed to answer what RH recorded: RH answers on the same screen.
     */
    public function store(RecordLeaveRequest $request, mixed $tenant, RequestLeave $ask, DecideRequest $decide): RedirectResponse
    {
        $employeeId = $request->string('employee_id')->toString();

        try {
            $recorded = $ask->handle($employeeId, $request->typeId(), $request->startsOn(), $request->endsOn(), $request->string('note')->toString(), $request->userId());
        } catch (RequestRefused $refused) {
            throw RecordLeaveRequest::refusal($refused);
        }

        $name = $this->roster->find($employeeId)->name ?? '';

        if (! $request->boolean('approve')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => "Solicitud registrada a nombre de {$name}."]);

            return back();
        }

        try {
            $decide->handle($recorded->id, true, Decider::hr($request->userId()));
            Inertia::flash('toast', ['type' => 'success', 'message' => "Solicitud registrada y aprobada a nombre de {$name}."]);
        } catch (RequestRefused $refused) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'La solicitud quedó registrada, pero sin aprobar: '.self::refusalText($refused)]);
        }

        return back();
    }

    /**
     * The active people whose name has what was typed, a few at a time, for the field that picks who a record is for.
     *
     * @return list<array{value: string, label: string, hint: string}>
     */
    public function people(Request $request): array
    {
        Gate::authorize('manage-vacations');
        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $found = array_values(array_filter($this->roster->page(new RosterQuery(search: $search), self::OPTIONS_LIMIT)->items(), fn (RosterEntry $person): bool => $person->active));
        $ids = array_map(fn (RosterEntry $person): string => $person->id, $found);
        $left = array_map(fn (array $periods): int => array_sum(array_map(fn (PeriodBalance $period): int => $period->left(), $periods)), Balances::of($ids));
        $advanced = Balances::advanced($ids);
        $waiting = Balances::waiting($ids);

        return array_map(fn (RosterEntry $person): array => [
            'value' => $person->id, 'label' => $person->name,
            'hint' => self::hint(($left[$person->id] ?? 0) + ($advanced[$person->id] ?? 0), $waiting[$person->id] ?? 0),
        ], $found);
    }

    /** «6 días disponibles · 2 en espera»: what a person has to ask for and what already waits for an answer. */
    private static function hint(int $available, int $waiting): string
    {
        $text = $available === 1 ? '1 día disponible' : "{$available} días disponibles";

        return $waiting > 0 ? "{$text} · {$waiting} en espera" : $text;
    }

    public function approve(DecisionRequest $request, mixed $tenant, string $leaveRequest, DecideRequest $decide): RedirectResponse
    {
        return $this->answer($leaveRequest, true, null, $request, $decide);
    }

    public function reject(DecisionRequest $request, mixed $tenant, string $leaveRequest, DecideRequest $decide): RedirectResponse
    {
        return $this->answer($leaveRequest, false, $request->string('reason')->toString(), $request, $decide);
    }

    /**
     * RH corrects: it cancels what still holds its dates, an enjoyed one too, and the days go back to the same periods.
     */
    public function cancel(DecisionRequest $request, mixed $tenant, string $leaveRequest, CancelRequest $cancel): RedirectResponse
    {
        try {
            $cancel->handle($leaveRequest, Decider::hr($request->userId()));
            Inertia::flash('toast', ['type' => 'success', 'message' => 'La solicitud quedó cancelada.']);
        } catch (AlreadyDecided) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Esta solicitud ya no se puede cancelar.']);
        }

        return back();
    }

    /**
     * One answer wins: a second one is told so and changes nothing. A request that cannot be approved without days says so.
     */
    private function answer(string $requestId, bool $approve, ?string $reason, DecisionRequest $request, DecideRequest $decide): RedirectResponse
    {
        try {
            $decide->handle($requestId, $approve, Decider::hr($request->userId()), $reason);
            Inertia::flash('toast', ['type' => 'success', 'message' => $approve ? 'Solicitud aprobada.' : 'Solicitud rechazada.']);
        } catch (AlreadyDecided $refused) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $refused->getMessage()]);
        } catch (RequestRefused $refused) {
            Inertia::flash('toast', ['type' => 'error', 'message' => self::refusalText($refused)]);
        }

        return back();
    }

    private static function refusalText(RequestRefused $refused): string
    {
        return $refused->reason === 'insufficient_balance' ? 'La persona no tiene días disponibles y su política no permite adelantar vacaciones.' : $refused->getMessage();
    }

    /**
     * The id a filter names: none when it is not asked for, a 404 when it is not a uuid or is not one `valid` accepts.
     *
     * @param  callable(string): bool  $valid
     */
    private function uuid(Request $request, string $key, callable $valid): ?string
    {
        $id = $request->string($key)->toString();

        if ($id === '') {
            return null;
        }

        return Str::isUuid($id) && $valid($id) ? $id : abort(404);
    }
}
