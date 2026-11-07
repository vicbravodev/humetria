<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\RecordMark;
use App\Modules\Attendance\Data\MarkContext;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Exceptions\InvalidMarkSequence;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;

mutates(RecordMark::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    workIn($this->tenant);
});

function onsite(): MarkContext
{
    return new MarkContext(MarkMethod::QrAndGeofence, null, 19.432608, -99.133209, 12.5, 30.0, null);
}

/** @param list<MarkType> $types */
function markAll(string $employeeId, array $types): void
{
    foreach ($types as $type) {
        resolve(RecordMark::class)->handle($employeeId, $type, onsite());
    }
}

it('stamps the mark with the server time and the person\'s center, with how it was made', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));

    $mark = resolve(RecordMark::class)->handle($this->ana->id, MarkType::Start, onsite());

    expect($mark->fresh())
        ->tenant_id->toBe($this->tenant->id)
        ->employee_id->toBe($this->ana->id)
        ->work_center_id->toBe($this->center->id)
        ->type->toBe(MarkType::Start)
        ->method->toBe(MarkMethod::QrAndGeofence)
        ->latitude->toBe('19.432608')
        ->longitude->toBe('-99.133209')
        ->accuracy_m->toBe(12.5)
        ->distance_m->toBe(30.0)
        ->and($mark->marked_at?->utc()->toDateTimeString())->toBe('2026-11-09 15:00:00');
});

it('accepts a full day with a break, and a new start after the end', function (): void {
    markAll($this->ana->id, [MarkType::Start, MarkType::BreakStart, MarkType::BreakEnd, MarkType::End, MarkType::Start, MarkType::End]);

    expect(AttendanceMark::query()->count())->toBe(6);
});

it('accepts an end during a break', function (): void {
    markAll($this->ana->id, [MarkType::Start, MarkType::BreakStart, MarkType::End]);

    expect(AttendanceMark::query()->count())->toBe(3);
});

it('rejects a mark that breaks the sequence and creates no row', function (array $before, MarkType $type, string $reason): void {
    markAll($this->ana->id, $before);

    expect(fn () => resolve(RecordMark::class)->handle($this->ana->id, $type, onsite()))
        ->toThrow(fn (InvalidMarkSequence $e) => expect($e->reason)->toBe($reason))
        ->and(AttendanceMark::query()->count())->toBe(count($before));
})->with([
    'end without a start' => [[], MarkType::End, 'day_not_open'],
    'a second start in a row' => [[MarkType::Start], MarkType::Start, 'day_already_open'],
    'a start during a break' => [[MarkType::Start, MarkType::BreakStart], MarkType::Start, 'day_already_open'],
    'a break without a start' => [[], MarkType::BreakStart, 'day_not_open'],
    'a break after the end' => [[MarkType::Start, MarkType::End], MarkType::BreakStart, 'day_not_open'],
    'a second break in a row' => [[MarkType::Start, MarkType::BreakStart], MarkType::BreakStart, 'already_on_break'],
    'a break end without a break' => [[MarkType::Start], MarkType::BreakEnd, 'not_on_break'],
    'a break end with no day' => [[], MarkType::BreakEnd, 'not_on_break'],
    'a second end' => [[MarkType::Start, MarkType::End], MarkType::End, 'day_not_open'],
]);

it('follows each person\'s own sequence', function (): void {
    $luis = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    markAll($this->ana->id, [MarkType::Start]);

    markAll($luis->id, [MarkType::Start]);

    expect(AttendanceMark::query()->count())->toBe(2);
});

it('takes the last mark by time, not by insertion', function (): void {
    AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'type' => MarkType::End, 'marked_at' => now()->subHour()]);
    AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'type' => MarkType::Start, 'marked_at' => now()->subHours(9)]);

    markAll($this->ana->id, [MarkType::Start]);

    expect(AttendanceMark::query()->count())->toBe(3);
});

it('does not let a deactivated person or one of another company mark', function (): void {
    $gone = EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $other = EmployeeFactory::new()->create();

    expect(fn () => markAll($gone->id, [MarkType::Start]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => markAll($other->id, [MarkType::Start]))->toThrow(InvalidArgumentException::class)
        ->and(AttendanceMark::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('logs the mark with ids and never the coordinates', function (): void {
    Log::spy();

    $mark = resolve(RecordMark::class)->handle($this->ana->id, MarkType::Start, onsite());

    Log::shouldHaveReceived('info')->once()->with('attendance.mark_recorded', [
        'mark_id' => $mark->id,
        'employee_id' => $this->ana->id,
        'type' => 'start',
        'method' => 'qr_and_geofence',
    ]);
});
