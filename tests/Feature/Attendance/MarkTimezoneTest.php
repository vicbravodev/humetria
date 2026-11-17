<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\CorrectMark;
use App\Modules\Attendance\Actions\RecordMark;
use App\Modules\Attendance\Actions\ResolveCorrection;
use App\Modules\Attendance\Data\MarkContext;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Each mark keeps the zone of the place it was taken (design of time zones, §3.4): marks are the legal record and are
 * append-only, so a center that changes its zone, or a law that changes, never moves an old mark to another day.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->tijuana = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Tijuana']);
    $this->cancun = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Cancun']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->tijuana->id]);
    workIn($this->tenant);
    agreementAccepted($this->ana->id);
});

function takenWithQr(?string $awayCenterId = null): MarkContext
{
    return new MarkContext(MarkMethod::Qr, null, null, null, null, null, null, $awayCenterId);
}

it('stamps a mark with the zone of the person\'s center', function (): void {
    $mark = resolve(RecordMark::class)->handle($this->ana->id, MarkType::Start, takenWithQr());

    expect($mark->fresh())
        ->timezone->toBe('America/Tijuana')
        ->away_center_id->toBeNull()
        ->work_center_id->toBe($this->tijuana->id);
});

it('stamps a mark taken at another center of the company with that center\'s zone, keeping the person\'s own center', function (): void {
    $mark = resolve(RecordMark::class)->handle($this->ana->id, MarkType::Start, takenWithQr($this->cancun->id));

    expect($mark->fresh())
        ->timezone->toBe('America/Cancun')
        ->away_center_id->toBe($this->cancun->id)
        ->work_center_id->toBe($this->tijuana->id);
});

it('keeps an old mark in its zone when its center changes zone', function (): void {
    $mark = resolve(RecordMark::class)->handle($this->ana->id, MarkType::Start, takenWithQr());

    $this->tijuana->update(['timezone' => 'America/Hermosillo']);

    expect($mark->fresh()?->timezone)->toBe('America/Tijuana');
});

it('gives a correction the zone of the mark it corrects', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 18:00', 'UTC'));
    $original = resolve(RecordMark::class)->handle($this->ana->id, MarkType::Start, takenWithQr($this->cancun->id));
    $this->tijuana->update(['timezone' => 'America/Hermosillo']);

    $correction = resolve(CorrectMark::class)->handle($original->id, CarbonImmutable::parse('2026-11-09 17:00', 'UTC'), 'Olvidó marcar.', ownerOf($this->tenant)->id);

    expect($correction->fresh())
        ->timezone->toBe('America/Cancun')
        ->away_center_id->toBe($this->cancun->id);
});

it('gives a forgotten mark RH adds the zone of the person\'s center', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 18:00', 'UTC'));
    $request = CorrectionRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->ana->id,
        'mark_id' => null,
        'type' => MarkType::Start,
        'proposed_at' => CarbonImmutable::parse('2026-11-09 16:00', 'UTC'),
    ]);

    $resolved = resolve(ResolveCorrection::class)->handle($request->id, true, ownerOf($this->tenant)->id, null);

    expect(AttendanceMark::query()->findOrFail($resolved->resulting_mark_id))
        ->timezone->toBe('America/Tijuana')
        ->away_center_id->toBeNull();
});

it('refuses in the database a mark with no zone or one that is not of Mexico', function (?string $zone, string $error): void {
    $mark = AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->tijuana->id]);

    expect(fn () => DB::insert(
        'insert into attendance_marks (id, tenant_id, employee_id, work_center_id, type, marked_at, method, timezone)
            select gen_random_uuid(), tenant_id, employee_id, work_center_id, type, now(), method, ? from attendance_marks where id = ?',
        [$zone, $mark->id],
    ))->toThrow(QueryException::class, $error);
})->with([
    'none' => [null, 'timezone'],
    'not of Mexico' => ['America/Los_Angeles', 'attendance_marks_timezone_known'],
]);

it('refuses in the database a mark away at a center of another company', function (): void {
    $other = WorkCenter::factory()->create();

    expect(fn () => AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->tijuana->id, 'away_center_id' => $other->id]))
        ->toThrow(QueryException::class, 'attendance_marks_away_center_id_tenant_id_foreign');
});

it('still refuses to change the zone of a mark: the marks stay append-only', function (): void {
    $mark = AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->tijuana->id, 'timezone' => 'America/Tijuana']);

    expect(fn () => DB::update("update attendance_marks set timezone = 'America/Mexico_City' where id = ?", [$mark->id]))
        ->toThrow(QueryException::class, 'attendance_marks is append-only');
});
