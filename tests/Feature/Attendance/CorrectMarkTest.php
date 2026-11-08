<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Attendance\Actions\CorrectMark;
use App\Modules\Attendance\Actions\RecordMark;
use App\Modules\Attendance\Data\MarkContext;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Exceptions\InvalidMarkSequence;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

mutates(CorrectMark::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->hr = User::factory()->create();
    workIn($this->tenant);
    $this->start = resolve(RecordMark::class)->handle($this->ana->id, MarkType::Start, new MarkContext(MarkMethod::QrAndGeofence, null, 19.432608, -99.133209, 10.0, 20.0, null));
});

it('inserts a new mark with who, why and the original, and leaves the original as it was', function (): void {
    $before = $this->start->fresh()?->getAttributes();
    $at = CarbonImmutable::parse('2026-11-04 15:00:00', 'UTC');

    $correction = resolve(CorrectMark::class)->handle($this->start->id, $at, 'Llegó a las 9, el QR falló.', $this->hr->id);

    expect($correction->fresh())
        ->method->toBe(MarkMethod::Correction)
        ->type->toBe(MarkType::Start)
        ->employee_id->toBe($this->ana->id)
        ->work_center_id->toBe($this->center->id)
        ->corrects_mark_id->toBe($this->start->id)
        ->corrected_by->toBe($this->hr->id)
        ->reason->toBe('Llegó a las 9, el QR falló.')
        ->latitude->toBeNull()
        ->and($correction->marked_at?->utc()->toDateTimeString())->toBe('2026-11-04 15:00:00')
        ->and($this->start->fresh()?->getAttributes())->toBe($before)
        ->and(EffectiveMarks::forWeek($this->ana->id, WeekPeriod::containing($at))[0]->id)->toBe($correction->id);
});

it('voids the original when the correction has no time, so the day is no longer open', function (): void {
    resolve(CorrectMark::class)->handle($this->start->id, null, 'Marcó por error.', $this->hr->id);

    expect(fn () => resolve(RecordMark::class)->handle($this->ana->id, MarkType::End, new MarkContext(MarkMethod::Remote, null, null, null, null, null, null)))
        ->toThrow(fn (InvalidMarkSequence $e) => expect($e->reason)->toBe('day_not_open'));
});

it('writes the correction to the audit trail and the log, with ids only', function (): void {
    Log::spy();

    $correction = resolve(CorrectMark::class)->handle($this->start->id, null, 'Marcó por error.', $this->hr->id);

    $entry = AuditEntry::query()->where('event', 'attendance.mark_corrected')->sole();
    expect($entry->subject_id)->toBe($correction->id)
        ->and($entry->metadata)->toEqual(['corrects_mark_id' => $this->start->id, 'employee_id' => $this->ana->id]);
    Log::shouldHaveReceived('info')->once()->with('attendance.mark_corrected', ['mark_id' => $correction->id, 'corrects_mark_id' => $this->start->id, 'employee_id' => $this->ana->id]);
});

it('does not correct a mark twice: the correction is the one to correct', function (): void {
    $first = resolve(CorrectMark::class)->handle($this->start->id, null, 'Marcó por error.', $this->hr->id);

    expect(fn () => resolve(CorrectMark::class)->handle($this->start->id, now()->toImmutable(), 'Otra vez.', $this->hr->id))->toThrow(InvalidArgumentException::class);

    $second = resolve(CorrectMark::class)->handle($first->id, now()->toImmutable(), 'Sí entró.', $this->hr->id);
    expect($second->corrects_mark_id)->toBe($first->id)
        ->and(AttendanceMark::query()->count())->toBe(3);
});

it('does not correct a mark to a time still to come, which would block the person\'s real marks', function (): void {
    $this->freezeTime();

    expect(fn () => resolve(CorrectMark::class)->handle($this->start->id, now()->toImmutable()->addSecond(), 'Adelantada.', $this->hr->id))
        ->toThrow(InvalidArgumentException::class, 'future')
        ->and(AttendanceMark::query()->count())->toBe(1);

    expect(resolve(CorrectMark::class)->handle($this->start->id, now()->toImmutable(), 'Justo ahora.', $this->hr->id)->method)->toBe(MarkMethod::Correction);
});

it('does not find a mark of another company', function (): void {
    $other = AttendanceMark::factory()->create();

    expect(fn () => resolve(CorrectMark::class)->handle($other->id, null, 'No.', $this->hr->id))->toThrow(ModelNotFoundException::class);
});
