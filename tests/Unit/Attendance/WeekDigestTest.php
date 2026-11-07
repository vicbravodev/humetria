<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

mutates(WeekDigest::class);

beforeEach(function (): void {
    config(['app.display_timezone' => 'America/Mexico_City']);
    $this->tenant = Tenant::factory()->create();
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    workIn($this->tenant);
    $this->week = WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-02'));
});

function digestMark(object $test, MarkType $type, string $local, ?string $employeeId = null): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $test->tenant->id, 'employee_id' => $employeeId ?? $test->ana->id, 'work_center_id' => $test->center->id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
    ]);
}

it('is the sha256 of the ids of the week\'s effective marks, sorted, so the same marks always give the same digest', function (): void {
    $start = digestMark($this, MarkType::Start, '2026-11-03 09:00');
    $end = digestMark($this, MarkType::End, '2026-11-03 17:00');
    $ids = [$start->id, $end->id];
    sort($ids);

    expect(WeekDigest::of($this->ana->id, $this->week))->toBe(hash('sha256', implode(',', $ids)))
        ->and(WeekDigest::of($this->ana->id, $this->week))->toBe(WeekDigest::of($this->ana->id, $this->week));
});

it('is the digest of nothing for a week with no marks', function (): void {
    expect(WeekDigest::of($this->ana->id, $this->week))->toBe(hash('sha256', ''));
});

it('changes with a correction and with a void', function (): void {
    $start = digestMark($this, MarkType::Start, '2026-11-03 09:20');
    $end = digestMark($this, MarkType::End, '2026-11-03 17:00');
    $before = WeekDigest::of($this->ana->id, $this->week);

    AttendanceMark::factory()->correcting($start)->create(['marked_at' => CarbonImmutable::parse('2026-11-03 09:00', 'America/Mexico_City')->utc()]);
    $corrected = WeekDigest::of($this->ana->id, $this->week);
    AttendanceMark::factory()->correcting($end)->create(['marked_at' => null]);

    expect($corrected)->not->toBe($before)
        ->and(WeekDigest::of($this->ana->id, $this->week))->not->toBe($corrected);
});

it('does not change with the marks of another week or another person', function (): void {
    digestMark($this, MarkType::Start, '2026-11-03 09:00');
    $before = WeekDigest::of($this->ana->id, $this->week);

    digestMark($this, MarkType::End, '2026-11-03 17:00', EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id);
    digestMark($this, MarkType::Start, '2026-11-10 09:00');

    expect(WeekDigest::of($this->ana->id, $this->week))->toBe($before);
});
