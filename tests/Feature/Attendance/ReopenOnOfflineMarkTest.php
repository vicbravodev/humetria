<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Events\OfflineMarkAccepted;
use App\Modules\Attendance\Listeners\ReopenOnOfflineMark;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

mutates(ReopenOnOfflineMark::class);

// A mark made without a connection that arrives after the person answered the week changes what they agreed to (criterio 5.2):
// a confirmed week goes back to pending and they confirm it again; one they disputed stays disputed, and Recursos Humanos
// sees the new mark there.

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
});

/** A mark of `$employeeId` at `$local`, Mexico City time, as a delivery without a connection would leave it. */
function offlineMarkAt(string $employeeId, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->offlineDeclared()->create([
        'tenant_id' => test()->tenant->id, 'employee_id' => $employeeId, 'work_center_id' => test()->center->id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
    ]);
}

function acceptedMark(AttendanceMark $mark): void
{
    event(new OfflineMarkAccepted($mark->id, $mark->employee_id, (string) Str::uuid()));
}

/** The week of the Monday `$monday`, answered (`confirmed` or `disputed`) or pending, for `$employeeId`. */
function weekOf(string $employeeId, string $monday, string $state = 'confirmed'): WeeklyConfirmation
{
    $factory = WeeklyConfirmation::factory();
    $factory = $state === 'pending' ? $factory : $factory->{$state}();

    return $factory->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employeeId, 'week_start' => $monday]);
}

it('sends a confirmed week back to pending and writes why to the audit trail, with the mark and no time', function (): void {
    $week = weekOf($this->ana->id, '2026-11-09');
    $mark = offlineMarkAt($this->ana->id, MarkType::Start, '2026-11-10 09:00');

    acceptedMark($mark);

    expect($week->fresh())->status->toBe(WeekStatus::Pending)->confirmed_at->toBeNull()->digest->toBeNull()->credential_id->toBeNull();
    expect(AuditEntry::query()->where('event', 'attendance.week_reopened')->sole())
        ->subject_id->toBe($week->id)
        ->metadata->toEqual(['employee_id' => $this->ana->id, 'week_start' => '2026-11-09', 'mark_id' => $mark->id]);
});

it('leaves a week the person disputed disputed', function (): void {
    $week = weekOf($this->ana->id, '2026-11-09', 'disputed');

    acceptedMark(offlineMarkAt($this->ana->id, MarkType::Start, '2026-11-10 09:00'));

    expect($week->fresh())->status->toBe(WeekStatus::Disputed)->reason->not->toBeNull()
        ->and(AuditEntry::query()->where('event', 'attendance.week_reopened')->count())->toBe(0);
});

it('leaves a week still pending, a week with no row, a week of the mark of another time and one of another person as they are', function (): void {
    $pending = weekOf($this->ana->id, '2026-11-09', 'pending');
    $other = weekOf($this->ana->id, '2026-11-02');
    $bruno = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $brunos = weekOf($bruno->id, '2026-11-09');

    // Ana's mark is of 10 November: her week of the 9th is pending, her week of the 2nd and Bruno's are not its own.
    acceptedMark(offlineMarkAt($this->ana->id, MarkType::Start, '2026-11-10 09:00'));
    // And a mark of a week with no row at all.
    acceptedMark(offlineMarkAt($this->ana->id, MarkType::Start, '2026-11-17 09:00'));

    expect($pending->fresh()?->status)->toBe(WeekStatus::Pending)
        ->and($other->fresh()?->status)->toBe(WeekStatus::Confirmed)
        ->and($brunos->fresh()?->status)->toBe(WeekStatus::Confirmed)
        ->and(WeeklyConfirmation::query()->where('week_start', '2026-11-17')->count())->toBe(0)
        ->and(AuditEntry::query()->where('event', 'attendance.week_reopened')->count())->toBe(0);
});

it('reopens the week of the Sunday a night shift started on when its end arrives on Monday', function (): void {
    offlineMarkAt($this->ana->id, MarkType::Start, '2026-11-08 22:00');
    $sunday = weekOf($this->ana->id, '2026-11-02');
    $later = weekOf($this->ana->id, '2026-11-16');

    acceptedMark(offlineMarkAt($this->ana->id, MarkType::End, '2026-11-09 06:00'));

    // The week of the clock's Monday is read too (a wider guess only touches one more); a week further on is not.
    expect($sunday->fresh()?->status)->toBe(WeekStatus::Pending)
        ->and($later->fresh()?->status)->toBe(WeekStatus::Confirmed);
});

it('runs inside the transaction of the delivery: if it is rolled back the week is still confirmed', function (): void {
    $week = weekOf($this->ana->id, '2026-11-09');
    $mark = offlineMarkAt($this->ana->id, MarkType::Start, '2026-11-10 09:00');

    try {
        DB::transaction(function () use ($mark): void {
            acceptedMark($mark);

            throw new RuntimeException('The delivery failed after the mark.');
        });
    } catch (RuntimeException) {
    }

    expect($week->fresh()?->status)->toBe(WeekStatus::Confirmed)
        ->and(AuditEntry::query()->where('event', 'attendance.week_reopened')->count())->toBe(0);
});
