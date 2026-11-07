<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Attendance\Actions\ResolveCorrection;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Events\MarkCorrected;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\CorrectedSequence;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

mutates(ResolveCorrection::class, CorrectedSequence::class);

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    // Monday 2 November: 9:20 to 18:00.
    $this->start = resolveMark($this->ana, MarkType::Start, '2026-11-02 09:20');
    $this->end = resolveMark($this->ana, MarkType::End, '2026-11-02 18:00');
});

function resolveMark(Employee $person, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
    ]);
}

/** A pending request of Ana for `$mark` (none: one she forgot) at a local time. */
function anaRequest(?AttendanceMark $mark, MarkType $type, string $local, string $reason = 'Llegué a las 9, el QR no cargaba.'): CorrectionRequest
{
    return CorrectionRequest::factory()->create([
        'tenant_id' => test()->tenant->id, 'employee_id' => test()->ana->id, 'mark_id' => $mark?->id, 'type' => $type,
        'proposed_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'reason' => $reason,
    ]);
}

function resolveAs(CorrectionRequest $request, bool $approve, ?string $note = null, ?User $by = null): CorrectionRequest
{
    return resolve(ResolveCorrection::class)->handle($request->id, $approve, (string) ($by ?? test()->hr)->id, $note);
}

describe('approving', function (): void {
    it('corrects the mark to the time asked, with the person\'s reason and who approved; the original stays', function (): void {
        $request = anaRequest($this->start, MarkType::Start, '2026-11-02 09:00');
        Log::spy();

        $resolved = resolveAs($request, true, 'Lo confirmó su jefa.');

        $correction = AttendanceMark::query()->where('corrects_mark_id', $this->start->id)->sole();
        expect($resolved->fresh())
            ->status->toBe(CorrectionStatus::Approved)
            ->resolved_by->toBe($this->hr->id)
            ->resolved_at->not->toBeNull()
            ->note->toBe('Lo confirmó su jefa.')
            ->resulting_mark_id->toBe($correction->id)
            ->and($correction)
            ->method->toBe(MarkMethod::Correction)
            ->corrected_by->toBe($this->hr->id)
            ->reason->toBe('Llegué a las 9, el QR no cargaba.')
            ->and($correction->marked_at?->toIso8601String())->toBe('2026-11-02T15:00:00+00:00')
            ->and($this->start->fresh())->not->toBeNull();

        $audit = AuditEntry::query()->where('event', 'attendance.correction_resolved')->sole();
        expect($audit->subject_id)->toBe($request->id)
            ->and($audit->metadata)->toEqual(['approved' => true, 'mark_id' => $correction->id, 'employee_id' => $this->ana->id])
            ->and(json_encode(AuditEntry::query()->get(), JSON_THROW_ON_ERROR))->not->toContain('QR no cargaba')->not->toContain('su jefa');
        Log::shouldHaveReceived('info')->with('attendance.correction_resolved', ['request_id' => $request->id, 'approved' => true, 'mark_id' => $correction->id, 'employee_id' => $this->ana->id]);
    });

    it('inserts exactly one correction mark for a mark the person forgot, at its UTC instant, even near midnight', function (): void {
        $request = anaRequest(null, MarkType::Start, '2026-11-03 09:00', 'Olvidé marcar mi entrada.');
        resolveMark($this->ana, MarkType::End, '2026-11-03 23:50');
        $before = AttendanceMark::query()->count();

        $resolved = resolveAs($request, true);

        $mark = AttendanceMark::query()->whereKey($resolved->resulting_mark_id)->sole();
        expect(AttendanceMark::query()->count())->toBe($before + 1)
            ->and($mark)
            ->method->toBe(MarkMethod::Correction)
            ->type->toBe(MarkType::Start)
            ->corrects_mark_id->toBeNull()
            ->corrected_by->toBe($this->hr->id)
            ->work_center_id->toBe($this->plant->id)
            ->reason->toBe('Olvidé marcar mi entrada.')
            ->latitude->toBeNull()
            ->and($mark->marked_at?->toIso8601String())->toBe('2026-11-03T15:00:00+00:00');
    });

    it('adds an end the person forgot at 23:50 local, the next day in UTC', function (): void {
        resolveMark($this->ana, MarkType::Start, '2026-11-03 15:00');
        $request = anaRequest(null, MarkType::End, '2026-11-03 23:50', 'Salí casi a medianoche.');

        $mark = AttendanceMark::query()->whereKey(resolveAs($request, true)->resulting_mark_id)->sole();

        expect($mark->marked_at?->toIso8601String())->toBe('2026-11-04T05:50:00+00:00');
    });

    it('reopens a confirmed week, for a correction and for a mark added', function (?string $which): void {
        $device = AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        $week = WeeklyConfirmation::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-02', 'status' => WeekStatus::Confirmed,
            'confirmed_at' => now(), 'device_id' => $device->id, 'digest' => WeekDigest::of($this->ana->id, WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-02'))),
        ]);
        $request = $which === 'start'
            ? anaRequest($this->start, MarkType::Start, '2026-11-02 09:00')
            : anaRequest(null, MarkType::Start, '2026-11-04 09:00', 'Olvidé marcar el miércoles.');

        resolveAs($request, true);

        expect($week->fresh()?->status)->toBe(WeekStatus::Pending);
    })->with(['a correction' => ['start'], 'a mark added' => [null]]);

    it('raises MarkCorrected for a mark added, with no original', function (): void {
        Event::fake([MarkCorrected::class]);
        $request = anaRequest(null, MarkType::Start, '2026-11-04 09:00', 'Olvidé marcar el miércoles.');

        $resolved = resolveAs($request, true);

        Event::assertDispatched(MarkCorrected::class, fn (MarkCorrected $event): bool => $event->markId === $resolved->resulting_mark_id
            && $event->correctsMarkId === null && $event->employeeId === $this->ana->id);
    });

    it('refuses an approval that leaves the day out of order, keeping the request pending and the marks as they were', function (string $case): void {
        $request = match ($case) {
            'an end before its start' => anaRequest($this->end, MarkType::End, '2026-11-02 08:00'),
            'a start moved after its end' => anaRequest($this->start, MarkType::Start, '2026-11-02 19:00'),
            'a start while the day is open' => anaRequest(null, MarkType::Start, '2026-11-02 12:00', 'Olvidé marcar mi regreso.'),
            'a break end with no break' => anaRequest(null, MarkType::BreakEnd, '2026-11-02 15:00', 'Regresé de comer a las 15.'),
            'an end with no start' => anaRequest(null, MarkType::End, '2026-11-04 18:00', 'Olvidé marcar mi salida.'),
        };
        $before = AttendanceMark::query()->count();

        expect(fn () => resolveAs($request, true))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('sequence_invalid'))
            ->and($request->fresh()?->status)->toBe(CorrectionStatus::Pending)
            ->and(AttendanceMark::query()->count())->toBe($before)
            ->and(AuditEntry::query()->where('event', 'attendance.correction_resolved')->count())->toBe(0);
    })->with(['an end before its start', 'a start moved after its end', 'a start while the day is open', 'a break end with no break', 'an end with no start']);

    it('takes a day put in order: a break inside it, an end the person forgot before the next start', function (): void {
        resolveMark($this->ana, MarkType::Start, '2026-11-03 09:00');
        resolveMark($this->ana, MarkType::Start, '2026-11-04 09:00');
        $break = anaRequest(null, MarkType::BreakStart, '2026-11-02 14:00', 'Salí a comer a las 14.');
        $end = anaRequest(null, MarkType::End, '2026-11-03 18:00', 'Olvidé marcar mi salida.');

        expect(resolveAs($break, true)->status)->toBe(CorrectionStatus::Approved)
            ->and(fn () => resolveAs(anaRequest(null, MarkType::End, '2026-11-02 13:00', 'Salí a la 1.'), true))->toThrow(CorrectionRejected::class)
            ->and(resolveAs($end, true)->status)->toBe(CorrectionStatus::Approved);
    });

    it('refuses to approve a request of a mark corrected since', function (): void {
        $request = anaRequest($this->start, MarkType::Start, '2026-11-02 09:00');
        AttendanceMark::factory()->correcting($this->start)->create();

        expect(fn () => resolveAs($request, true))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('mark_changed'))
            ->and($request->fresh()?->status)->toBe(CorrectionStatus::Pending);
    });

    it('locks the request while resolving it', function (): void {
        $request = anaRequest($this->start, MarkType::Start, '2026-11-02 09:00');
        DB::enableQueryLog();

        resolveAs($request, false, 'Llegaste a las 9:20.');

        expect(collect(DB::getQueryLog())->pluck('query')->contains(fn (string $sql): bool => str_contains($sql, 'from "attendance_correction_requests"') && str_ends_with($sql, 'for update')))->toBeTrue();
    });
});

describe('rejecting', function (): void {
    it('keeps the note and inserts nothing', function (): void {
        $request = anaRequest($this->start, MarkType::Start, '2026-11-02 09:00');
        $before = AttendanceMark::query()->count();

        $resolved = resolveAs($request, false, '  Llegaste a las 9:20, lo dice la cámara.  ');

        expect($resolved->fresh())->status->toBe(CorrectionStatus::Rejected)->note->toBe('Llegaste a las 9:20, lo dice la cámara.')->resulting_mark_id->toBeNull()->resolved_by->toBe($this->hr->id)
            ->and(AttendanceMark::query()->count())->toBe($before)
            ->and(AuditEntry::query()->where('event', 'attendance.correction_resolved')->sole()->metadata)->toEqual(['approved' => false, 'mark_id' => null, 'employee_id' => $this->ana->id]);
    });

    it('needs a note of 5 characters', function (?string $note): void {
        $request = anaRequest($this->start, MarkType::Start, '2026-11-02 09:00');

        expect(fn () => resolveAs($request, false, $note))->toThrow(InvalidArgumentException::class)
            ->and($request->fresh()?->status)->toBe(CorrectionStatus::Pending);
    })->with([null, '   ', 'No.']);
});

describe('once', function (): void {
    it('resolves a request once: a second approval or rejection is refused', function (bool $first, bool $second): void {
        $request = anaRequest(null, MarkType::Start, '2026-11-04 09:00', 'Olvidé marcar el miércoles.');
        resolveAs($request, $first, 'Revisado con su jefa.');
        $marks = AttendanceMark::query()->count();

        expect(fn () => resolveAs($request, $second, 'Revisado otra vez.'))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('already_resolved'))
            ->and(AttendanceMark::query()->count())->toBe($marks)
            ->and($request->fresh()?->status)->toBe($first ? CorrectionStatus::Approved : CorrectionStatus::Rejected);
    })->with([[true, true], [true, false], [false, true], [false, false]]);

    it('is resolved only by a member of the company, and only in it', function (): void {
        $request = anaRequest($this->start, MarkType::Start, '2026-11-02 09:00');
        $outsider = memberOf(Tenant::factory()->create(), TenantRole::Hr);
        workIn($this->tenant);

        expect(fn () => resolveAs($request, true, by: User::factory()->create()))->toThrow(InvalidArgumentException::class)
            ->and(fn () => resolveAs($request, true, by: $outsider))->toThrow(InvalidArgumentException::class)
            ->and(fn () => resolve(ResolveCorrection::class)->handle($request->id, true, 'x', null))->toThrow(InvalidArgumentException::class)
            ->and($request->fresh()?->status)->toBe(CorrectionStatus::Pending);
    });

    it('does not find a request of another company', function (): void {
        $other = CorrectionRequest::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        expect(fn () => resolveAs($other, false, 'Llegaste a las 9:20.'))->toThrow(ModelNotFoundException::class);
    });
});
