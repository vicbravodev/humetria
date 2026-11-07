<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Attendance\Actions\CloseDays;
use App\Modules\Attendance\Actions\ConfirmWeek;
use App\Modules\Attendance\Actions\CorrectMark;
use App\Modules\Attendance\Actions\DisputeWeek;
use App\Modules\Attendance\Actions\ReopenWeeks;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Events\WeekConfirmed;
use App\Modules\Attendance\Events\WeekDisputed;
use App\Modules\Attendance\Exceptions\WeekRejected;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

mutates(ConfirmWeek::class, DisputeWeek::class, ReopenWeeks::class, CloseDays::class);

const LAST_WEEK = '2026-11-02';

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City: the week of 2 November is over, the one of the 9th is not.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->device = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

function weekMark(Employee $person, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
    ]);
}

function localDate(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'America/Mexico_City');
}

function confirmWeek(object $test, string $week = LAST_WEEK): WeeklyConfirmation
{
    return resolve(ConfirmWeek::class)->handle($test->ana->id, localDate($week), $test->device->id);
}

function disputeWeek(object $test, string $reason = 'El jueves salí a las 20:00, no a las 18:00.', string $week = LAST_WEEK): WeeklyConfirmation
{
    return resolve(DisputeWeek::class)->handle($test->ana->id, localDate($week), $reason, $test->device->id);
}

describe('confirming', function (): void {
    it('confirms the week with the moment and the verified device, to the log and the audit trail', function (): void {
        Event::fake([WeekConfirmed::class]);
        Log::spy();

        $week = confirmWeek($this);

        expect($week->fresh())
            ->employee_id->toBe($this->ana->id)
            ->status->toBe(WeekStatus::Confirmed)
            ->device_id->toBe($this->device->id)
            ->disputed_at->toBeNull()
            ->reason->toBeNull()
            ->and($week->week_start->toDateString())->toBe(LAST_WEEK)
            ->and($week->confirmed_at?->equalTo(now()))->toBeTrue();
        Event::assertDispatched(WeekConfirmed::class, fn (WeekConfirmed $event): bool => $event->confirmationId === $week->id && $event->employeeId === $this->ana->id && $event->weekStart === LAST_WEEK);
        $entry = AuditEntry::query()->where('event', 'attendance.week_confirmed')->sole();
        expect($entry->subject_id)->toBe($week->id)
            ->and($entry->metadata)->toEqual(['employee_id' => $this->ana->id, 'week_start' => LAST_WEEK, 'device_id' => $this->device->id]);
        Log::shouldHaveReceived('info')->with('attendance.week_confirmed', ['confirmation_id' => $week->id, 'employee_id' => $this->ana->id, 'week_start' => LAST_WEEK, 'device_id' => $this->device->id]);
    });

    it('takes any day of the week as the week', function (): void {
        expect(confirmWeek($this, '2026-11-08')->week_start->toDateString())->toBe(LAST_WEEK);
    });

    it('keeps the first confirmation when confirmed again', function (): void {
        $first = confirmWeek($this);
        $this->travel(1)->hour();

        $again = confirmWeek($this);

        expect($again->id)->toBe($first->id)
            ->and($again->confirmed_at?->equalTo($first->confirmed_at))->toBeTrue()
            ->and(WeeklyConfirmation::query()->count())->toBe(1)
            ->and(AuditEntry::query()->where('event', 'attendance.week_confirmed')->count())->toBe(1);
    });

    it('confirms a week that the nightly close already left pending', function (): void {
        weekMark($this->ana, MarkType::Start, '2026-11-03 09:00');
        resolve(CloseDays::class)->handle();

        expect(confirmWeek($this)->status)->toBe(WeekStatus::Confirmed)
            ->and(WeeklyConfirmation::query()->count())->toBe(1);
    });

    it('rejects a week that is not over: the current one up to its last second', function (): void {
        expect(fn () => confirmWeek($this, '2026-11-09'))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('week_not_over'));

        $this->travelTo(CarbonImmutable::parse('2026-11-15 23:59:59', 'America/Mexico_City'));
        expect(fn () => confirmWeek($this, '2026-11-09'))->toThrow(WeekRejected::class);

        $this->travelTo(CarbonImmutable::parse('2026-11-16 00:00:00', 'America/Mexico_City'));
        expect(confirmWeek($this, '2026-11-09')->status)->toBe(WeekStatus::Confirmed);
    });

    it('rejects a disputed week: RH resolves it', function (): void {
        disputeWeek($this);

        expect(fn () => confirmWeek($this))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('week_disputed'));
        expect(WeeklyConfirmation::query()->sole()->status)->toBe(WeekStatus::Disputed);
    });
});

describe('disputing', function (): void {
    it('disputes the week with the reason, the moment and the device, to the log and the audit trail without the reason', function (): void {
        Event::fake([WeekDisputed::class]);
        Log::spy();

        $week = disputeWeek($this, '  El jueves salí a las 20:00, no a las 18:00.  ');

        expect($week->fresh())
            ->status->toBe(WeekStatus::Disputed)
            ->reason->toBe('El jueves salí a las 20:00, no a las 18:00.')
            ->device_id->toBe($this->device->id)
            ->confirmed_at->toBeNull()
            ->and($week->disputed_at?->equalTo(now()))->toBeTrue();
        Event::assertDispatched(WeekDisputed::class, fn (WeekDisputed $event): bool => $event->confirmationId === $week->id && $event->weekStart === LAST_WEEK);
        expect(AuditEntry::query()->where('event', 'attendance.week_disputed')->sole()->metadata)
            ->toEqual(['employee_id' => $this->ana->id, 'week_start' => LAST_WEEK, 'device_id' => $this->device->id]);
        Log::shouldHaveReceived('info')->with('attendance.week_disputed', ['confirmation_id' => $week->id, 'employee_id' => $this->ana->id, 'week_start' => LAST_WEEK, 'device_id' => $this->device->id]);
    });

    it('requires a reason of at least 10 characters', function (string $reason): void {
        expect(fn () => disputeWeek($this, $reason))->toThrow(InvalidArgumentException::class);
        expect(WeeklyConfirmation::query()->count())->toBe(0);
    })->with(['empty' => [''], 'blank' => ['          '], 'too short' => ['No es así']]);

    it('lets the person dispute a week they had confirmed', function (): void {
        confirmWeek($this);

        expect(disputeWeek($this))->status->toBe(WeekStatus::Disputed)->confirmed_at->toBeNull()
            ->and(WeeklyConfirmation::query()->count())->toBe(1);
    });

    it('rejects disputing a week twice', function (): void {
        disputeWeek($this);

        expect(fn () => disputeWeek($this, 'Otra cosa distinta que corregir.'))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('already_disputed'));
        expect(WeeklyConfirmation::query()->sole()->reason)->toBe('El jueves salí a las 20:00, no a las 18:00.');
    });

    it('rejects a week that is not over', function (): void {
        expect(fn () => disputeWeek($this, week: '2026-11-10'))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('week_not_over'));
    });
});

describe('a correction', function (): void {
    beforeEach(function (): void {
        $this->hr = User::factory()->create();
        $this->start = weekMark($this->ana, MarkType::Start, '2026-11-04 09:20');
        weekMark($this->ana, MarkType::End, '2026-11-04 18:00');
    });

    it('reopens a confirmed week to pending once inserted, to the log and the audit trail', function (): void {
        $week = confirmWeek($this);
        Log::spy();

        resolve(CorrectMark::class)->handle($this->start->id, localDate('2026-11-04 09:00'), 'Llegó a las 9, el QR falló.', (string) $this->hr->id);

        expect($week->fresh())->status->toBe(WeekStatus::Pending)->confirmed_at->toBeNull()->device_id->toBeNull();
        expect(AuditEntry::query()->where('event', 'attendance.week_reopened')->sole()->subject_id)->toBe($week->id);
        Log::shouldHaveReceived('info')->with('attendance.week_reopened', ['confirmation_id' => $week->id, 'employee_id' => $this->ana->id, 'week_start' => LAST_WEEK]);
    });

    it('leaves a disputed week disputed, for RH to resolve', function (): void {
        disputeWeek($this);

        resolve(CorrectMark::class)->handle($this->start->id, null, 'Marcó por error.', (string) $this->hr->id);

        expect(WeeklyConfirmation::query()->sole()->status)->toBe(WeekStatus::Disputed);
    });

    it('leaves the confirmation of another week alone', function (): void {
        $other = resolve(ConfirmWeek::class)->handle($this->ana->id, localDate('2026-10-26'), $this->device->id);
        confirmWeek($this);

        resolve(CorrectMark::class)->handle($this->start->id, localDate('2026-11-04 09:00'), 'Llegó a las 9, el QR falló.', (string) $this->hr->id);

        expect($other->fresh()?->status)->toBe(WeekStatus::Confirmed);
    });

    it('reopens the week of the day a Monday end belongs to: the Sunday night it started', function (): void {
        weekMark($this->ana, MarkType::Start, '2026-11-08 22:00');
        $end = weekMark($this->ana, MarkType::End, '2026-11-09 06:00');
        $week = confirmWeek($this);

        resolve(CorrectMark::class)->handle($end->id, localDate('2026-11-09 07:00'), 'Se quedó una hora más.', (string) $this->hr->id);

        expect($week->fresh()?->status)->toBe(WeekStatus::Pending);
    });

    it('reopens the week a correction moves a mark into', function (): void {
        $week = resolve(ConfirmWeek::class)->handle($this->ana->id, localDate('2026-10-26'), $this->device->id);

        resolve(CorrectMark::class)->handle($this->start->id, localDate('2026-10-30 09:00'), 'Era del viernes anterior.', (string) $this->hr->id);

        expect($week->fresh()?->status)->toBe(WeekStatus::Pending);
    });
});

describe('the nightly close', function (): void {
    beforeEach(function (): void {
        // Monday 9 November 2026, 00:15: the week of 2 November just ended.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 00:15:00', 'America/Mexico_City'));
    });

    it('leaves one week pending for each active person with marks in the week that ended, and only one when run twice', function (): void {
        weekMark($this->ana, MarkType::Start, '2026-11-02 09:00');
        weekMark($this->ana, MarkType::End, '2026-11-02 17:00');
        Log::spy();

        resolve(CloseDays::class)->handle();
        resolve(CloseDays::class)->handle();

        $week = WeeklyConfirmation::query()->sole();
        expect($week)->employee_id->toBe($this->ana->id)->status->toBe(WeekStatus::Pending)->confirmed_at->toBeNull()
            ->and($week->week_start->toDateString())->toBe(LAST_WEEK);
        Log::shouldHaveReceived('info')->with('attendance.week_opened', ['confirmation_id' => $week->id, 'employee_id' => $this->ana->id, 'week_start' => LAST_WEEK])->once();
    });

    it('keeps the week pending in silence: nobody confirms it for the person', function (): void {
        weekMark($this->ana, MarkType::Start, '2026-11-02 09:00');
        resolve(CloseDays::class)->handle();
        $this->travel(90)->days();

        resolve(CloseDays::class)->handle();

        expect(WeeklyConfirmation::query()->sole()->status)->toBe(WeekStatus::Pending);
    });

    it('opens no week for a person who left, nor for a week with no marks or only voided ones', function (): void {
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        weekMark($bob, MarkType::Start, '2026-11-03 09:00');
        $bob->update(['deactivated_at' => now()]);
        $carla = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        AttendanceMark::factory()->correcting(weekMark($carla, MarkType::Start, '2026-11-03 09:00'))->create(['marked_at' => null]);
        weekMark($this->ana, MarkType::Start, '2026-10-30 09:00');

        resolve(CloseDays::class)->handle();

        expect(WeeklyConfirmation::query()->count())->toBe(0);
    });

    it('opens the week of a Sunday night that ends on Monday', function (): void {
        weekMark($this->ana, MarkType::Start, '2026-11-08 22:00');

        resolve(CloseDays::class)->handle();

        expect(WeeklyConfirmation::query()->sole()->week_start->toDateString())->toBe(LAST_WEEK);
    });
});

describe('the table', function (): void {
    it('keeps one row per person and week', function (): void {
        WeeklyConfirmation::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => LAST_WEEK]);

        expect(fn () => WeeklyConfirmation::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => LAST_WEEK]))
            ->toThrow(QueryException::class, 'attendance_weekly_confirmations_employee_id_week_start_unique');
    });

    it('starts every week on a Monday', function (): void {
        expect(fn () => WeeklyConfirmation::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-03']))
            ->toThrow(QueryException::class, 'attendance_weekly_confirmations_week_start_monday');
    });

    it('holds a moment and a device only as its status says', function (array $attributes): void {
        expect(fn () => WeeklyConfirmation::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, ...$attributes]))
            ->toThrow(QueryException::class, 'attendance_weekly_confirmations_status_consistent');
    })->with([
        'confirmed without moment' => [['status' => 'confirmed', 'device_id' => null]],
        'disputed without reason' => [['status' => 'disputed', 'disputed_at' => now()]],
        'pending with a moment' => [['status' => 'pending', 'confirmed_at' => now()]],
    ]);
});
