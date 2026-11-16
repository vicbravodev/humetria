<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Modules\Leave\Actions\AssignPolicy;
use App\Modules\Leave\Actions\GrantEntitlements;
use App\Modules\Leave\Actions\RequestLeave;
use App\Modules\Leave\Actions\SaveHoliday;
use App\Modules\Leave\Actions\SavePolicy;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Enums\RowKind;
use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Database\Seeders\Demo\DemoStory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;

/**
 * Vacaciones at Alba del Bajío. The company started using the tool a year ago, with one policy for everyone: the law, a
 * birthday day and 30 % of premium. Since then each person got, on their anniversary, the period of that year of service
 * with the days of the 2027 table (criterios C-01, C-03 and C-21); whoever has not completed a year has none. About forty
 * took vacations months ago, one took a paid leave, four are about to go (one is away from today), three were turned
 * down, and six ask for days that wait for their manager, asked today or yesterday. Nobody kept for the door asked for anything, and no day
 * off lands on a day with attendance marks.
 *
 * Asking goes through RequestLeave, so the working days, the period and the manager are the module's own. Answering does
 * not: DecideRequest takes a Decider, which the module keeps in Support, closed to the story. The answer writes what
 * DecideRequest writes for a manager who answers a request the period covers (the ledger rows of its allocation and the
 * decision on the request; a drift guard in the test compares both), and a vacation that already ended is written taken,
 * as the nightly sweep leaves it.
 */
final readonly class LeaveStory implements DemoStory
{
    private const int TRACKING_DAYS = 365;

    private const string POLICY = 'Prestaciones Alba del Bajío';

    /** The company's holidays besides those of art. 74, by month and day. */
    private const array HOLIDAYS = ['11-02' => 'Día de Muertos', '12-12' => 'Día de la Virgen de Guadalupe', '12-24' => 'Nochebuena', '12-31' => 'Fin de año'];

    /** The days a history request spans from its Monday: a week (5 working days), two weeks (10) and the 12 in a row of art. 78. */
    private const array SPANS = [4, 11, 15];

    private const array REJECTIONS = [
        'Esa semana es el cierre de inventario; ¿la movemos a la siguiente?',
        'Ya hay dos personas del área fuera esos días. Propón otras fechas, por favor.',
        'Coincide con la auditoría del cliente. Hablemos para buscar otra semana.',
    ];

    private const string SPECIAL_PAID = 'special_paid';

    public function __construct(
        private SavePolicy $policy,
        private AssignPolicy $assign,
        private SaveHoliday $holiday,
        private GrantEntitlements $grant,
        private RequestLeave $request,
    ) {}

    public function tell(DemoContext $context): void
    {
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        DemoClock::at('-'.self::TRACKING_DAYS.' days', fn () => $this->setUp($context, $today));
        $anniversaries = $this->grants($today);
        $counts = $this->requests($today, $anniversaries);

        Log::info('demo.leave_seeded', ['periods' => count($anniversaries), ...$counts]);
    }

    /** The company starts using Vacaciones, as the panel does the first time it opens, with its policy and its holidays. */
    private function setUp(DemoContext $context, CarbonImmutable $today): void
    {
        $since = CarbonImmutable::now(DemoClock::TIMEZONE);
        LeaveSetting::query()->create(['tenant_id' => $context->tenantId, 'tracking_since' => $since->toDateString(), 'statutory_work_week' => WorkWeek::MonFri]);
        LeaveType::query()->insertOrIgnore(array_map(fn (array $type): array => [
            'id' => (string) Str::uuid(), 'tenant_id' => $context->tenantId, 'key' => $type[0], 'name' => $type[1], 'paid' => $type[2], 'created_at' => now(), 'updated_at' => now(),
        ], LeaveType::SEEDED));

        $policy = $this->policy->handle([
            'name' => self::POLICY, 'premium_percent' => 30, 'work_week' => WorkWeek::MonFri->value, 'allows_advance' => false,
            'extra_expires_months' => null, 'extra_carryover_cap_days' => null,
            'rows' => [['kind' => RowKind::Fixed->value, 'label' => 'Día de cumpleaños', 'days' => 1]],
        ]);
        $this->assign->handle(PolicyScope::Default, $policy->id);

        foreach (range($since->year, $today->year + 1) as $year) {
            foreach (self::HOLIDAYS as $day => $name) {
                $this->holiday->handle(CarbonImmutable::parse("{$year}-{$day}"), $name);
            }
        }
    }

    /**
     * Everyone's period of their last anniversary, granted that day: the first year after the start reaches every
     * anniversary of the last twelve months.
     *
     * @return array<string, CarbonImmutable> the anniversary, by the key of whoever has one
     */
    private function grants(CarbonImmutable $today): array
    {
        $anniversaries = [];

        foreach (DemoRoster::people() as $person) {
            $hired = $today->subDays($person->tenureDays);

            for ($years = 1; ($day = $hired->addYearsNoOverflow($years))->lessThanOrEqualTo($today); $years++) {
                $anniversaries[$person->key] = $day;
            }

            if (isset($anniversaries[$person->key])) {
                DemoClock::at($anniversaries[$person->key]->toDateString(), fn (): int => $this->grant->grantFor(DemoRoster::employeeId($person->key)));
            }
        }

        return $anniversaries;
    }

    /**
     * One request a person at most, so each is answered against the period it was asked with: the history first (every
     * other person whose vacations fit before their attendance marks), then from the rest the ones about to go, the
     * ones turned down, a leave and the ones still waiting.
     *
     * @param  array<string, CarbonImmutable>  $anniversaries
     * @return array<string, int>
     */
    private function requests(CarbonImmutable $today, array $anniversaries): array
    {
        $counts = ['taken' => 0, 'approved' => 0, 'rejected' => 0, 'pending' => 0];
        $rest = [];

        foreach ($this->candidates($anniversaries) as $n => $person) {
            $asked = $anniversaries[$person->key]->addDays(5 + $n % 20);
            $starts = $asked->addDays(10)->next(CarbonImmutable::MONDAY);
            $ends = $starts->addDays(self::SPANS[$n % 3]);

            if ($n % 2 === 0 && $ends->lessThan($this->marksSince($person, $today))) {
                $this->answer($this->ask($person, $asked, $starts, $ends), $asked->addDay(), $today);
                $counts['taken']++;
            } else {
                $rest[] = $person;
            }
        }

        foreach ($this->slots($today) as [$status, $asked, $starts, $ends, $reason]) {
            $person = $this->take($rest, $anniversaries, $asked);
            $type = $status === null ? LeaveType::query()->where('key', self::SPECIAL_PAID)->value('id') : null;
            $request = $this->ask($person, $asked, $starts, $ends, is_string($type) ? $type : null);

            if ($status !== RequestStatus::Requested) {
                $this->answer($request, $asked->addDay(), $today, $reason);
            }

            $counts[match ($status) {
                RequestStatus::Requested => 'pending',
                RequestStatus::Rejected => 'rejected',
                default => $request->fresh()?->status === RequestStatus::Taken ? 'taken' : 'approved',
            }]++;
        }

        return $counts;
    }

    /**
     * The requests besides the history, as [what becomes of it (null: an approved leave), asked, first day, last day,
     * reason of a rejection]: one away from today, three more approved, three turned down, a paid leave months ago and
     * six waiting.
     *
     * @return list<array{RequestStatus|null, CarbonImmutable, CarbonImmutable, CarbonImmutable, string|null}>
     */
    private function slots(CarbonImmutable $today): array
    {
        $slots = [[RequestStatus::Approved, $today->subDays(21), $today, $today->addDays(6), null]];

        for ($k = 1; $k <= 3; $k++) {
            $starts = $today->addWeeks($k)->next(CarbonImmutable::MONDAY);
            $slots[] = [RequestStatus::Approved, $today->subDays(10 + 3 * $k), $starts, $starts->addDays(4), null];
        }

        foreach (self::REJECTIONS as $k => $reason) {
            $asked = $today->subDays(40 + 9 * $k);
            $starts = $asked->addDays(14)->next(CarbonImmutable::MONDAY);
            $slots[] = [RequestStatus::Rejected, $asked, $starts, $starts->addDays(4), $reason];
        }

        // Before anyone's marks: the plant's shifts reach back six months.
        $starts = $today->subDays(200)->next(CarbonImmutable::MONDAY);
        $slots[] = [null, $starts->subDays(10), $starts, $starts->addDays(2), null];

        for ($k = 0; $k < 6; $k++) {
            $starts = $today->addWeeks(2 + $k)->next(CarbonImmutable::MONDAY);
            $slots[] = [RequestStatus::Requested, $today->subDays($k % 2), $starts, $starts->addDays($k % 2 === 0 ? 11 : 4), null];
        }

        return $slots;
    }

    /**
     * Who may ask: not kept for the door, with a manager to answer and a period to ask from.
     *
     * @param  array<string, CarbonImmutable>  $anniversaries
     * @return list<DemoPerson>
     */
    private function candidates(array $anniversaries): array
    {
        return array_values(array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => ! $person->reservedForDoor && $person->reportsTo !== null && isset($anniversaries[$person->key])));
    }

    /**
     * The first of the rest who already had their period the day they ask.
     *
     * @param  list<DemoPerson>  $rest
     * @param  array<string, CarbonImmutable>  $anniversaries
     */
    private function take(array &$rest, array $anniversaries, CarbonImmutable $asked): DemoPerson
    {
        foreach ($rest as $i => $person) {
            if ($anniversaries[$person->key]->lessThan($asked)) {
                array_splice($rest, $i, 1);

                return $person;
            }
        }

        throw new LogicException('The demo has nobody left to ask for days off.');
    }

    /** From when the person has attendance marks (AttendanceStory): six months on the plant's shifts, four weeks elsewhere. */
    private function marksSince(DemoPerson $person, CarbonImmutable $today): CarbonImmutable
    {
        $shift = $person->centerKey === 'plant' && in_array($person->area, ['Producción turno 1', 'Producción turno 2'], true);

        return $today->subDays($shift ? 26 * 7 + 1 : 4 * 7 + 1);
    }

    private function ask(DemoPerson $person, CarbonImmutable $asked, CarbonImmutable $starts, CarbonImmutable $ends, ?string $typeId = null): LeaveRequest
    {
        $request = DemoClock::at($asked->toDateString(), fn (): LeaveRequest => $this->request->handle(DemoRoster::employeeId($person->key), $typeId, $starts, $ends));

        if ($request->approver_employee_id === null || $request->short_by > 0 || $request->had_balance === false) {
            throw new LogicException("The demo's request of [{$person->key}] is not one their manager can approve from their period.");
        }

        return $request;
    }

    /**
     * What DecideRequest writes when the manager answers: approving vacations charges the allocation the request was
     * asked with (nothing changed the period since), a leave uses no balance, and a rejection keeps its reason.
     */
    private function answer(LeaveRequest $request, CarbonImmutable $on, CarbonImmutable $today, ?string $reason = null): void
    {
        DemoClock::at($on->toDateString(), fn () => DB::transaction(function () use ($request, $today, $reason): void {
            foreach ($reason === null ? $request->allocation : [] as $line) {
                LeaveEntry::query()->create([
                    'tenant_id' => $request->tenant_id, 'employee_id' => $request->employee_id, 'kind' => EntryKind::Taken, 'leave_request_id' => $request->id,
                    'leave_entitlement_id' => $line['entitlement_id'], 'tranche' => $line['tranche'], 'days' => -$line['days'],
                ]);
            }

            $request->update([
                'status' => $reason !== null ? RequestStatus::Rejected : ($request->ends_on->toDateString() < $today->toDateString() ? RequestStatus::Taken : RequestStatus::Approved),
                'decided_by_employee_id' => $request->approver_employee_id,
                'decided_by_user_id' => null,
                'decided_at' => now(),
                'rejection_reason' => $reason,
            ]);
        }), 10 * 60);
    }
}
