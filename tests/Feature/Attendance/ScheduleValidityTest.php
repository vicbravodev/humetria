<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\SaveSchedule;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

mutates(SaveSchedule::class, AttendanceSchedule::class);

const WEEK_9_TO_6 = [1 => 540, 2 => 540, 3 => 540, 4 => 540, 5 => 540, 6 => 0, 7 => 0];

/** How the audit trail keeps a week: a list from Monday to Sunday, which the audit page reads as one. */
const WEEK_9_TO_6_LIST = [540, 540, 540, 540, 540, 0, 0];

beforeEach(function (): void {
    // Wednesday 11 Nov 2026, noon in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
});

describe('the migration', function (): void {
    it('gives the schedules that already exist «since always», and going back keeps the one in effect today', function (): void {
        $migration = require database_path('migrations/2027_01_28_100000_add_valid_from_to_attendance_schedules.php');
        $legacy = AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 18:00:00', 'UTC'));
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'valid_from' => '2026-11-01', 'minutes_by_weekday' => WEEK_9_TO_6]);
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'valid_from' => '2026-12-01']);

        $migration->down();

        expect(Schema::hasColumn('attendance_schedules', 'valid_from'))->toBeFalse()
            ->and(DB::table('attendance_schedules')->where('employee_id', $this->ana->id)->pluck('minutes_by_weekday')->map(fn (string $json): mixed => json_decode($json, true))->all())
            ->toBe([WEEK_9_TO_6])
            ->and(DB::table('attendance_schedules')->where('id', $legacy->id)->exists())->toBeFalse();

        $migration->up();

        expect(DB::table('attendance_schedules')->where('employee_id', $this->ana->id)->value('valid_from'))->toBe('1970-01-01');
    });
});

describe('saving a schedule with a start date', function (): void {
    it('starts today by default and keeps the schedule it replaces', function (): void {
        $first = resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Day, WorkMode::Onsite, WEEK_9_TO_6, null, CarbonImmutable::parse('2026-01-01'));
        $second = resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Day, WorkMode::Onsite, [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0]);

        expect(AttendanceSchedule::query()->where('employee_id', $this->ana->id)->orderBy('valid_from')->pluck('valid_from')->map->toDateString()->all())
            ->toBe(['2026-01-01', '2026-11-11'])
            ->and($second->valid_from->toDateString())->toBe('2026-11-11')
            ->and($first->fresh()->minutes_by_weekday)->toBe(WEEK_9_TO_6);
    });

    it('takes today in the company\'s zone, not in UTC', function (): void {
        // 04:00 UTC of the 12th is still the 11th, 22:00, in Mexico City.
        $this->travelTo(CarbonImmutable::parse('2026-11-12 04:00:00', 'UTC'));

        $schedule = resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Day, WorkMode::Onsite, WEEK_9_TO_6);

        expect($schedule->valid_from->toDateString())->toBe('2026-11-11');
    });

    it('changes in place the schedule that starts the same day', function (): void {
        $first = resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Day, WorkMode::Onsite, WEEK_9_TO_6, null, CarbonImmutable::parse('2026-12-01'));
        $again = resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Night, WorkMode::Remote, WEEK_9_TO_6, null, CarbonImmutable::parse('2026-12-01'));

        expect(AttendanceSchedule::query()->where('employee_id', $this->ana->id)->sole())
            ->id->toBe($first->id)
            ->and($again->id)->toBe($first->id)
            ->and($again->shift_type)->toBe(ShiftType::Night);
    });

    it('lets RH start one in the past, to correct, and puts it in its place', function (): void {
        resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Day, WorkMode::Onsite, WEEK_9_TO_6, null, CarbonImmutable::parse('2026-03-01'));
        resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Day, WorkMode::Onsite, WEEK_9_TO_6, null, CarbonImmutable::parse('2026-06-01'));
        resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Night, WorkMode::Onsite, WEEK_9_TO_6, null, CarbonImmutable::parse('2026-04-01'));

        expect(AttendanceSchedule::query()->where('employee_id', $this->ana->id)->orderBy('valid_from')->get()->map(fn (AttendanceSchedule $s): string => $s->valid_from->toDateString().' '.$s->shift_type->value)->all())
            ->toBe(['2026-03-01 day', '2026-04-01 night', '2026-06-01 day']);
    });

    it('records in the audit trail what it replaced and what it left, without a person\'s name', function (): void {
        $before = resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Day, WorkMode::Onsite, WEEK_9_TO_6, [1 => 540, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null], CarbonImmutable::parse('2026-01-01'));
        $after = resolve(SaveSchedule::class)->handle($this->ana->id, ShiftType::Mixed, WorkMode::Remote, [1 => 420, 2 => 420, 3 => 420, 4 => 420, 5 => 420, 6 => 0, 7 => 0], null, CarbonImmutable::parse('2026-03-01'));

        $entries = AuditEntry::query()->where('event', 'attendance.schedule_saved')->orderBy('created_at')->orderBy('id')->get();

        expect($entries)->toHaveCount(2)
            ->and($entries[0]->metadata)->toEqual([
                'employee_id' => $this->ana->id,
                'valid_from' => '2026-01-01',
                'before' => null,
                'after' => ['valid_from' => '2026-01-01', 'shift_type' => 'day', 'work_mode' => 'onsite', 'minutes' => WEEK_9_TO_6_LIST, 'starts' => [540, null, null, null, null, null, null]],
            ])
            ->and($entries[1]->subject_id)->toBe($after->id)
            ->and($entries[1]->metadata['before'])->toEqual(['valid_from' => '2026-01-01', 'shift_type' => 'day', 'work_mode' => 'onsite', 'minutes' => WEEK_9_TO_6_LIST, 'starts' => [540, null, null, null, null, null, null]])
            ->and($entries[1]->metadata['after'])->toEqual(['valid_from' => '2026-03-01', 'shift_type' => 'mixed', 'work_mode' => 'remote', 'minutes' => [420, 420, 420, 420, 420, 0, 0], 'starts' => null])
            ->and($before->id)->not->toBe($after->id)
            ->and(collect($entries)->map(fn (AuditEntry $entry): string => auditContent($entry))->implode(' '))->not->toContain($this->ana->name);
    });
});
