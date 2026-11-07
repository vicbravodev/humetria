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
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

/** The digest of Ana's week as she sees it now. */
function seenDigest(object $test, string $week = LAST_WEEK): string
{
    return WeekDigest::of($test->ana->id, WeekPeriod::ofDate(localDate($week)));
}

function confirmWeek(object $test, string $week = LAST_WEEK, ?string $digest = null): WeeklyConfirmation
{
    return resolve(ConfirmWeek::class)->handle($test->ana->id, localDate($week), $test->device->id, $digest ?? seenDigest($test, $week));
}

function disputeWeek(object $test, string $reason = 'El jueves salí a las 20:00, no a las 18:00.', string $week = LAST_WEEK, ?string $digest = null): WeeklyConfirmation
{
    return resolve(DisputeWeek::class)->handle($test->ana->id, localDate($week), $reason, $test->device->id, $digest ?? seenDigest($test, $week));
}

/** Ana worked a whole day, 9:00 to 17:00, on a local date. */
function workedOn(object $test, string $date): void
{
    weekMark($test->ana, MarkType::Start, "{$date} 09:00");
    weekMark($test->ana, MarkType::End, "{$date} 17:00");
}

describe('confirming', function (): void {
    beforeEach(fn () => workedOn($this, '2026-11-03'));

    it('confirms the week with the moment and the verified device, to the log and the audit trail', function (): void {
        Event::fake([WeekConfirmed::class]);
        Log::spy();

        $week = confirmWeek($this);

        expect($week->fresh())
            ->employee_id->toBe($this->ana->id)
            ->status->toBe(WeekStatus::Confirmed)
            ->device_id->toBe($this->device->id)
            ->digest->toBe(seenDigest($this))
            ->disputed_at->toBeNull()
            ->reason->toBeNull()
            ->and($week->week_start->toDateString())->toBe(LAST_WEEK)
            ->and($week->confirmed_at?->equalTo(now()))->toBeTrue();
        Event::assertDispatched(WeekConfirmed::class, fn (WeekConfirmed $event): bool => $event->confirmationId === $week->id && $event->employeeId === $this->ana->id && $event->weekStart === LAST_WEEK);
        $entry = AuditEntry::query()->where('event', 'attendance.week_confirmed')->sole();
        expect($entry->subject_id)->toBe($week->id)
            ->and($entry->metadata)->toEqual(['employee_id' => $this->ana->id, 'week_start' => LAST_WEEK, 'device_id' => $this->device->id, 'digest' => seenDigest($this)]);
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
        resolve(CloseDays::class)->handle();

        expect(confirmWeek($this)->status)->toBe(WeekStatus::Confirmed)
            ->and(WeeklyConfirmation::query()->count())->toBe(1);
    });

    it('rejects a week that is not over: the current one up to its last second', function (): void {
        workedOn($this, '2026-11-10');
        expect(fn () => confirmWeek($this, '2026-11-09'))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('week_not_over'));

        $this->travelTo(CarbonImmutable::parse('2026-11-15 23:59:59', 'America/Mexico_City'));
        expect(fn () => confirmWeek($this, '2026-11-09'))->toThrow(WeekRejected::class);

        $this->travelTo(CarbonImmutable::parse('2026-11-16 00:00:00', 'America/Mexico_City'));
        expect(confirmWeek($this, '2026-11-09')->status)->toBe(WeekStatus::Confirmed);
    });

    it('rejects a week with no marks of the person: there is nothing to agree to', function (): void {
        expect(fn () => confirmWeek($this, '2026-10-26'))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('no_marks'));
        expect(WeeklyConfirmation::query()->where('status', WeekStatus::Confirmed)->count())->toBe(0);
    });

    it('rejects a week whose last day is still open, until its end is marked', function (): void {
        weekMark($this->ana, MarkType::Start, '2026-11-08 22:00');
        $this->travelTo(CarbonImmutable::parse('2026-11-09 00:30', 'America/Mexico_City'));

        expect(fn () => confirmWeek($this))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('day_open'));
        expect(WeeklyConfirmation::query()->where('status', WeekStatus::Confirmed)->count())->toBe(0);

        $this->travelTo(CarbonImmutable::parse('2026-11-09 06:00', 'America/Mexico_City'));
        weekMark($this->ana, MarkType::End, '2026-11-09 06:00');
        expect(confirmWeek($this)->status)->toBe(WeekStatus::Confirmed);
    });

    it('rejects a confirmation of a version of the week that changed since the person read it, changing nothing', function (): void {
        $seen = seenDigest($this);
        AttendanceMark::factory()->correcting(AttendanceMark::query()->where('type', MarkType::End)->sole())
            ->create(['marked_at' => CarbonImmutable::parse('2026-11-03 18:00', 'America/Mexico_City')->utc()]);

        expect(fn () => confirmWeek($this, digest: $seen))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('week_changed'));
        expect(WeeklyConfirmation::query()->where('status', '!=', WeekStatus::Pending)->count())->toBe(0)
            ->and(AuditEntry::query()->where('event', 'attendance.week_confirmed')->count())->toBe(0);
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
            ->digest->toBe(seenDigest($this))
            ->confirmed_at->toBeNull()
            ->and($week->disputed_at?->equalTo(now()))->toBeTrue();
        Event::assertDispatched(WeekDisputed::class, fn (WeekDisputed $event): bool => $event->confirmationId === $week->id && $event->weekStart === LAST_WEEK);
        expect(AuditEntry::query()->where('event', 'attendance.week_disputed')->sole()->metadata)
            ->toEqual(['employee_id' => $this->ana->id, 'week_start' => LAST_WEEK, 'device_id' => $this->device->id, 'digest' => seenDigest($this)]);
        Log::shouldHaveReceived('info')->with('attendance.week_disputed', ['confirmation_id' => $week->id, 'employee_id' => $this->ana->id, 'week_start' => LAST_WEEK, 'device_id' => $this->device->id]);
    });

    it('requires a reason of at least 10 characters', function (string $reason): void {
        expect(fn () => disputeWeek($this, $reason))->toThrow(InvalidArgumentException::class);
        expect(WeeklyConfirmation::query()->count())->toBe(0);
    })->with(['empty' => [''], 'blank' => ['          '], 'too short' => ['No es así']]);

    it('lets the person dispute a week they had confirmed', function (): void {
        workedOn($this, '2026-11-03');
        confirmWeek($this);

        expect(disputeWeek($this))->status->toBe(WeekStatus::Disputed)->confirmed_at->toBeNull()
            ->and(WeeklyConfirmation::query()->count())->toBe(1);
    });

    it('lets the person dispute a week with no marks: theirs may be missing', function (): void {
        expect(disputeWeek($this))->status->toBe(WeekStatus::Disputed);
    });

    it('rejects a dispute of a version of the week that changed since the person read it', function (): void {
        $seen = seenDigest($this);
        workedOn($this, '2026-11-03');

        expect(fn () => disputeWeek($this, digest: $seen))->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('week_changed'));
        expect(WeeklyConfirmation::query()->where('status', WeekStatus::Disputed)->count())->toBe(0);
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

        expect($week->fresh())->status->toBe(WeekStatus::Pending)->confirmed_at->toBeNull()->device_id->toBeNull()->digest->toBeNull();
        expect(AuditEntry::query()->where('event', 'attendance.week_reopened')->sole()->subject_id)->toBe($week->id);
        Log::shouldHaveReceived('info')->with('attendance.week_reopened', ['confirmation_id' => $week->id, 'employee_id' => $this->ana->id, 'week_start' => LAST_WEEK]);
    });

    it('leaves a disputed week disputed, for RH to resolve', function (): void {
        disputeWeek($this);

        resolve(CorrectMark::class)->handle($this->start->id, null, 'Marcó por error.', (string) $this->hr->id);

        expect(WeeklyConfirmation::query()->sole()->status)->toBe(WeekStatus::Disputed);
    });

    it('leaves the confirmation of another week alone', function (): void {
        workedOn($this, '2026-10-27');
        $other = confirmWeek($this, '2026-10-26');
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

    it('locks the rows of the weeks it touches, whatever their state, so an answer in flight waits for it', function (): void {
        confirmWeek($this);
        DB::enableQueryLog();

        resolve(CorrectMark::class)->handle($this->start->id, localDate('2026-11-04 09:00'), 'Llegó a las 9, el QR falló.', (string) $this->hr->id);

        // A confirmation holds its row locked while it checks the digest: the reopening waits for it and then sees it
        // confirmed. Locking a pending row too makes a confirmation that comes after wait for the correction and see its mark.
        $locks = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'from "attendance_weekly_confirmations"') && str_ends_with($sql, 'for update'));
        expect($locks)->toHaveCount(1)
            ->and($locks->first())->not->toContain('"status"');
    });

    it('takes the person\'s lock on their marks before the row of the week, to confirm and to dispute', function (string $answer): void {
        DB::enableQueryLog();

        $answer === 'confirm' ? confirmWeek($this) : disputeWeek($this);

        $queries = collect(DB::getQueryLog());
        $marksLock = $queries->search(fn (array $query): bool => str_contains($query['query'], 'pg_advisory_xact_lock') && $query['bindings'] === ["attendance_marks:{$this->ana->id}"]);
        $weekRow = $queries->search(fn (array $query): bool => str_contains($query['query'], '"attendance_weekly_confirmations"'));
        // After the row would deadlock against a correction, which takes the marks' lock and then the row.
        expect($marksLock)->toBeInt()->and($weekRow)->toBeInt()->and($marksLock)->toBeLessThan($weekRow);
    })->with(['confirm', 'dispute']);

    it('refuses an answer when a correction lands while it waits for the person\'s lock: the week it reads is the new one', function (string $answer): void {
        // The first answer of a week has no row to lock: what holds it back is the lock of the person's marks. A correction
        // that commits while the answer waits on it is simulated by adding a mark the moment the lock is granted.
        $seen = seenDigest($this);
        $landed = false;
        DB::listen(function ($query) use (&$landed): void {
            if (! $landed && str_contains($query->sql, 'pg_advisory_xact_lock') && $query->bindings === ["attendance_marks:{$this->ana->id}"]) {
                $landed = true;
                workedOn($this, '2026-11-06');
            }
        });

        expect(fn () => $answer === 'confirm' ? confirmWeek($this, digest: $seen) : disputeWeek($this, digest: $seen))
            ->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('week_changed'))
            ->and($landed)->toBeTrue()
            ->and(WeeklyConfirmation::query()->where('status', '!=', WeekStatus::Pending)->count())->toBe(0);
    })->with(['confirm', 'dispute']);

    it('reopens the week a correction moves a mark into', function (): void {
        workedOn($this, '2026-10-27');
        $week = confirmWeek($this, '2026-10-26');

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
        'pending with a digest' => [['status' => 'pending', 'digest' => str_repeat('a', 64)]],
    ]);

    it('keeps the digest of the week an answer was given on', function (?string $digest): void {
        expect(fn () => WeeklyConfirmation::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'status' => 'confirmed', 'confirmed_at' => now(), 'device_id' => $this->device->id, 'digest' => $digest,
        ]))->toThrow(QueryException::class, 'attendance_weekly_confirmations_status_consistent');
    })->with(['none' => [null], 'not a sha256' => ['abc']]);
});
