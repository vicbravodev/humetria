<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\CorrectMarkDirectly;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;

mutates(CorrectMarkDirectly::class);

// H09: RH corrects a mark without a request first (a person who left cannot ask), with a note, as CorrectMark does.

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->start = directMark($this->ana, MarkType::Start, '2026-11-03 09:20');
    $this->end = directMark($this->ana, MarkType::End, '2026-11-03 17:00');
});

function directMark(Employee $person, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
    ]);
}

function correctDirectly(object $test, ?string $local, string $note = 'Llegó a las 9, el lector falló.', ?AttendanceMark $mark = null): AttendanceMark
{
    return resolve(CorrectMarkDirectly::class)->handle(($mark ?? $test->start)->id, $local, $note, $test->hr->id);
}

describe('the action', function (): void {
    it('corrects the mark to the local time RH says, with the note, by RH, to the log and the audit trail', function (): void {
        Log::spy();

        $correction = correctDirectly($this, '2026-11-03 09:00');

        Log::shouldHaveReceived('info')->with('attendance.mark_corrected_directly', ['mark_id' => $correction->id, 'corrects_mark_id' => $this->start->id, 'employee_id' => $this->ana->id]);

        expect($correction)
            ->corrects_mark_id->toBe($this->start->id)->method->toBe(MarkMethod::Correction)
            ->reason->toBe('Llegó a las 9, el lector falló.')->corrected_by->toBe($this->hr->id)
            ->and($correction->marked_at?->equalTo(CarbonImmutable::parse('2026-11-03 09:00', 'America/Mexico_City')))->toBeTrue();
        expect(AuditEntry::query()->where('event', 'attendance.mark_corrected')->sole()->subject_id)->toBe($correction->id);
    });

    it('voids a mark without a time', function (): void {
        expect(correctDirectly($this, null, 'Marcó dos veces por error.', $this->end)->marked_at)->toBeNull();
    });

    it('reopens the answered week it touches', function (): void {
        $week = WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-02']);

        correctDirectly($this, '2026-11-03 09:00');

        expect($week->fresh()?->status)->toBe(WeekStatus::Pending);
    });

    it('corrects the marks of a person who left: they can no longer ask', function (): void {
        $this->ana->update(['deactivated_at' => now(), 'terminated_on' => '2026-11-04']);

        expect(correctDirectly($this, '2026-11-03 09:00')->corrects_mark_id)->toBe($this->start->id);
    });

    it('refuses a time that would leave the day out of order, one to come, and a mark already corrected', function (): void {
        expect(fn () => correctDirectly($this, '2026-11-03 18:00'))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('sequence_invalid'))
            ->and(fn () => correctDirectly($this, '2026-11-12 09:00'))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('future_time'));

        correctDirectly($this, '2026-11-03 09:00');

        expect(fn () => correctDirectly($this, '2026-11-03 09:05'))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('mark_changed'))
            ->and(AttendanceMark::query()->where('corrects_mark_id', $this->start->id)->count())->toBe(1);
    });

    it('requires a note of at least 10 characters', function (string $note): void {
        expect(fn () => correctDirectly($this, '2026-11-03 09:00', $note))->toThrow(InvalidArgumentException::class);
        expect(AttendanceMark::query()->whereNotNull('corrects_mark_id')->count())->toBe(0);
    })->with(['empty' => [''], 'too short' => ['Ya está']]);
});

describe('from the report', function (): void {
    it('corrects with the note and goes back with the news', function (): void {
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.marks.correct', ['acme', $this->start->id]), ['at' => '2026-11-03 09:00', 'note' => 'Llegó a las 9, el lector falló.'])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Marca corregida. Queda en la bitácora a tu nombre, con tu nota.']);

        expect(AttendanceMark::query()->where('corrects_mark_id', $this->start->id)->count())->toBe(1);
    });

    it('voids a mark when asked', function (): void {
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.marks.correct', ['acme', $this->end->id]), ['void' => true, 'note' => 'Marcó dos veces por error.'])->assertSessionHasNoErrors();

        expect(AttendanceMark::query()->where('corrects_mark_id', $this->end->id)->sole()->marked_at)->toBeNull();
    });

    it('says why it refuses a time', function (): void {
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.marks.correct', ['acme', $this->start->id]), ['at' => '2026-11-03 18:00', 'note' => 'Llegó a las 6, el lector falló.'])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Con esa hora el día queda en desorden (una salida antes de su entrada o una entrada con otra abierta). Revisa la hora.']);
    });

    it('asks for the note and for a time unless it voids the mark', function (): void {
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.marks.correct', ['acme', $this->start->id]), ['note' => 'Ya'])->assertSessionHasErrors(['note', 'at']);
    });

    it('does not let a viewer correct', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->post(route('tenant.attendance.marks.correct', ['acme', $this->start->id]), ['at' => '2026-11-03 09:00', 'note' => 'Llegó a las 9, el lector falló.'])->assertForbidden();

        expect(AttendanceMark::query()->whereNotNull('corrects_mark_id')->count())->toBe(0);
    });

    it('answers 404 to a mark of another company, by its URL', function (): void {
        $globex = Tenant::factory()->create();
        $foreign = AttendanceMark::factory()->create(['tenant_id' => $globex->id]);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.marks.correct', ['acme', $foreign->id]), ['at' => '2026-11-03 09:00', 'note' => 'Llegó a las 9, el lector falló.'])->assertNotFound();
    });
});
