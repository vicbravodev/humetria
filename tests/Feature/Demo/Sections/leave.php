<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Actions\RequestLeave;
use App\Modules\Leave\Contracts\Absences;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\PolicyScope;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\StatutoryHolidays;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;

/** The days of a year of service by the table of art. 76 since 2023 (criterio C-01), written apart from the module. */
function demoStatutoryDays(int $years): int
{
    return $years <= 5 ? 10 + 2 * $years : 20 + 2 * (int) ceil(($years - 5) / 5);
}

/**
 * The person's last anniversary up to today and its year of service, or null before the first one.
 *
 * @return array{int, CarbonImmutable}|null
 */
function demoLastAnniversary(DemoPerson $person, CarbonImmutable $today): ?array
{
    $hired = $today->subDays($person->tenureDays);
    $last = null;

    for ($years = 1; ($day = $hired->addYearsNoOverflow($years))->lessThanOrEqualTo($today); $years++) {
        $last = [$years, $day];
    }

    return $last;
}

/**
 * The working days of the vacations (no leave type) among the requests.
 *
 * @param  Illuminate\Support\Collection<int, LeaveRequest>  $requests
 */
function demoWorkingDays(Illuminate\Support\Collection $requests): int
{
    return array_sum($requests->whereNull('leave_type_id')->map(fn (LeaveRequest $request): int => $request->working_days)->all());
}

/*
 * The leave story's groups: DemoStoriesTest includes this file inside its one test, on the prepared demo, so `$this` is
 * that test.
 */
return [
    'tells the leave story: a policy, periods and balances, approved, rejected and pending requests, and holidays' => function (): void {
        $tenantId = resolve(DemoCompanies::class)->id();
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        $people = DemoRoster::people();
        $byId = [];
        foreach ($people as $person) {
            $byId[DemoRoster::employeeId($person->key)] = $person;
        }
        $door = array_keys(array_filter($byId, fn (DemoPerson $person): bool => $person->reservedForDoor));
        $balances = LeaveEntry::query()->groupBy('employee_id')->selectRaw('employee_id, sum(days) as days')->pluck('days', 'employee_id')->map(fn (mixed $days): int => is_numeric($days) ? (int) $days : 0);

        // has a policy, entitlements and balances for everyone: the company has used Vacaciones for a year, with one policy
        // by default (the law plus a birthday day and 30 % of premium), and everyone with a year of service has the period of
        // their last anniversary, with the days of the 2027 table, its statement and its days in the ledger; nobody before
        // their first anniversary has any (criterio C-03).
        $setting = LeaveSetting::query()->sole();
        $policy = LeavePolicy::query()->sole();

        expect($setting->tracking_since->toDateString())->toBe($today->subDays(365)->toDateString())
            ->and($policy->premium_percent)->toBe(30)
            ->and(LeavePolicyAssignment::query()->sole()->only(['scope', 'leave_policy_id']))->toBe(['scope' => PolicyScope::Default, 'leave_policy_id' => $policy->id])
            ->and(LeaveType::query()->count())->toBe(count(LeaveType::SEEDED));

        $periods = LeaveEntitlement::query()->get()->keyBy('employee_id');
        $withPeriod = 0;

        foreach ($byId as $employeeId => $person) {
            $last = demoLastAnniversary($person, $today);

            if ($last === null) {
                expect($periods->has($employeeId))->toBeFalse()
                    ->and($balances->get($employeeId))->toBeNull();

                continue;
            }

            [$years, $anniversary] = $last;
            $period = $periods->get($employeeId);
            $withPeriod++;

            expect($period)->not->toBeNull()
                ->and($period?->years_of_service)->toBe($years)
                ->and($period?->anniversary_on->toDateString())->toBe($anniversary->toDateString())
                ->and($period?->granted_on->toDateString())->toBe($anniversary->toDateString())
                ->and($period?->statutory_days)->toBe(demoStatutoryDays($years))
                ->and($period?->extra_days)->toBe(1)
                ->and($period?->premium_percent)->toBe(30);
        }

        expect($periods)->toHaveCount($withPeriod)
            ->and($withPeriod)->toBeGreaterThan(150)
            ->and(LeaveStatement::query()->count())->toBe($withPeriod)
            ->and($balances->keys()->sort()->values()->all())->toBe($periods->keys()->sort()->values()->all());

        // Opening the panel, as RH will, finds the company already set up: nothing changes.
        resolve(LeaveDefaults::class)->ensure();

        expect(LeaveSetting::query()->sole()->tracking_since->toDateString())->toBe($setting->tracking_since->toDateString())
            ->and(LeaveType::query()->count())->toBe(count(LeaveType::SEEDED));

        // has approved, rejected and pending requests, with pending ones routed to a manager: nobody kept for the door asked
        // for anything, the pending ones wait for the person's own manager and were asked today or yesterday (the sweep has not
        // escalated them), and every approved vacation took its days from the period in the ledger.
        $requests = LeaveRequest::query()->get();
        $pending = $requests->where('status', RequestStatus::Requested);
        $rejected = $requests->where('status', RequestStatus::Rejected);
        $approved = $requests->whereIn('status', [RequestStatus::Approved, RequestStatus::Taken]);

        expect($requests->pluck('employee_id')->intersect($door)->all())->toBe([])
            ->and($pending->count())->toBeGreaterThanOrEqual(5)
            ->and($rejected->count())->toBeGreaterThanOrEqual(2)
            ->and($approved->where('status', RequestStatus::Taken)->count())->toBeGreaterThanOrEqual(20)
            ->and($approved->where('status', RequestStatus::Approved)->count())->toBeGreaterThanOrEqual(2)
            ->and($approved->whereNotNull('leave_type_id')->count())->toBeGreaterThanOrEqual(1)
            ->and($requests->pluck('employee_id')->duplicates()->all())->toBe([]);

        foreach ($pending as $request) {
            $manager = $byId[$request->employee_id]->reportsTo;

            expect($request->approver_employee_id)->toBe($manager === null ? null : DemoRoster::employeeId($manager))
                ->and($request->approver_employee_id)->not->toBeNull()
                ->and($request->escalated_at)->toBeNull()
                ->and($request->requested_on->toDateString() >= $today->subDay()->toDateString())->toBeTrue()
                ->and($request->starts_on->toDateString() > $today->toDateString())->toBeTrue()
                ->and($request->had_balance)->toBeTrue();
        }

        foreach ([...$rejected, ...$approved] as $request) {
            expect($request->decided_by_employee_id)->toBe($request->approver_employee_id)
                ->and($request->decided_by_employee_id)->not->toBeNull()
                ->and($request->decided_at?->greaterThan($request->created_at))->toBeTrue();
        }

        expect($rejected->every(fn (LeaveRequest $request): bool => mb_strlen($request->rejection_reason ?? '') >= 5))->toBeTrue();

        foreach ($approved as $request) {
            $taken = LeaveEntry::query()->where('leave_request_id', $request->id)->get();
            $vacation = $request->leave_type_id === null;

            expect($request->status)->toBe($request->ends_on->toDateString() < $today->toDateString() ? RequestStatus::Taken : RequestStatus::Approved)
                ->and($taken->every(fn (LeaveEntry $entry): bool => $entry->kind === EntryKind::Taken && $entry->leave_entitlement_id === $periods->get($request->employee_id)?->id))->toBeTrue()
                ->and(array_sum($taken->map(fn (LeaveEntry $entry): int => $entry->days)->all()))->toBe($vacation ? -$request->working_days : 0)
                // nobody marked attendance on a day off
                ->and(AttendanceMark::query()->where('employee_id', $request->employee_id)->where('marked_at', '>=', $request->starts_on->shiftTimezone(DemoClock::TIMEZONE)->utc())
                    ->where('marked_at', '<', $request->ends_on->shiftTimezone(DemoClock::TIMEZONE)->addDay()->utc())->exists())->toBeFalse();
        }

        // the balance is what was granted less what was taken: no adjustment, no expiry, no advance
        expect(LeaveEntry::query()->whereNotIn('kind', [EntryKind::Granted, EntryKind::Taken])->count())->toBe(0)
            ->and(LeaveEntry::query()->whereNull('leave_entitlement_id')->count())->toBe(0);

        foreach ($periods as $employeeId => $period) {
            $days = demoWorkingDays($approved->where('employee_id', $employeeId));

            expect($balances->get($employeeId))->toBe($period->statutory_days + $period->extra_days - $days);
        }

        // someone is away today, and nobody kept for the door
        $away = resolve(Absences::class)->absentOn($today, array_keys($byId));

        expect($away)->not->toBe([])
            ->and(array_intersect($away, $door))->toBe([]);

        // has holidays for the year: the company's own, from the year it started up to the next one, none a day of art. 74
        $holidays = LeaveHoliday::query()->get();

        foreach (range($setting->tracking_since->year, $today->year + 1) as $year) {
            $ofYear = $holidays->filter(fn (LeaveHoliday $holiday): bool => $holiday->date->year === $year);

            expect($ofYear)->not->toBeEmpty()
                ->and($ofYear->map(fn (LeaveHoliday $holiday): string => $holiday->date->toDateString())->intersect(StatutoryHolidays::for($year))->all())->toBe([]);
        }

        // never gives a balance below zero: nor once the vacations still waiting are answered
        foreach ($balances as $employeeId => $balance) {
            expect($balance - demoWorkingDays($pending->where('employee_id', $employeeId)))->toBeGreaterThanOrEqual(0);
        }

        // nothing outside the demo company
        foreach ([LeaveSetting::class, LeavePolicy::class, LeaveEntitlement::class, LeaveEntry::class, LeaveRequest::class, LeaveHoliday::class, LeaveStatement::class] as $model) {
            expect($model::query()->withoutGlobalScopes()->where('tenant_id', '!=', $tenantId)->count())->toBe(0);
        }

        // An approval of the story has the shape of one DecideRequest writes: the same columns filled, the same ledger rows.
        $story = $approved->where('status', RequestStatus::Approved)->whereNull('leave_type_id')->firstOrFail();
        $person = collect($people)->first(fn (DemoPerson $person): bool => ! $person->reservedForDoor && $person->reportsTo !== null
            && $periods->has(DemoRoster::employeeId($person->key)) && ! $requests->contains('employee_id', DemoRoster::employeeId($person->key)))
            ?? throw new RuntimeException('Nobody left to ask.');
        $employeeId = DemoRoster::employeeId($person->key);
        $asked = resolve(RequestLeave::class)->handle($employeeId, null, $today->addWeeks(10)->startOfWeek(), $today->addWeeks(10)->startOfWeek()->addDays(4));
        $decided = resolve(DecideRequest::class)->handle($asked->id, true, Decider::manager($asked->approver_employee_id ?? ''))->fresh() ?? throw new RuntimeException('No request.');
        $filled = fn (LeaveRequest $request): array => array_keys(array_filter($request->getAttributes(), fn (mixed $value): bool => $value !== null));
        $entry = fn (LeaveRequest $request): array => LeaveEntry::query()->where('leave_request_id', $request->id)->get()
            ->map(fn (LeaveEntry $row): array => [array_keys(array_filter($row->getAttributes(), fn (mixed $value): bool => $value !== null)), $row->kind, $row->tranche])->all();

        expect($filled($story))->toEqualCanonicalizing($filled($decided))
            ->and($story->allocation[0] ?? null)->toHaveKeys(['entitlement_id', 'tranche', 'days'])
            ->and($story->short_by)->toBe($decided->short_by)
            ->and($entry($story)[0])->toEqual($entry($decided)[0]);
    },
];
