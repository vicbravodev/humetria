<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Attendance\Actions\ReviewOfflineMark;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\ReviewDecision;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Events\OfflineMarkReviewed;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\MarkReview;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Support\OfflineDeliveries;

mutates(ReviewOfflineMark::class);

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function review(string $markId, ReviewDecision $decision, ?string $note = null, ?string $local = null, ?User $by = null): MarkReview
{
    return resolve(ReviewOfflineMark::class)->handle($markId, $decision, ($by ?? test()->hr)->id, $note, $local);
}

/** The reason a review was refused for. */
function reviewRefusedFor(Closure $review): string
{
    try {
        $review();
    } catch (CorrectionRejected $refused) {
        return $refused->reason;
    }

    return '';
}

/** @param list<string> $ids */
function verifyMany(array $ids): array
{
    return resolve(ReviewOfflineMark::class)->verifyMany($ids, test()->hr->id);
}

describe('verifying', function (): void {
    it('only writes the review: no mark, no change of time, for a mark with a QR and for one without', function (bool $qr): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00', qr: $qr);
        $before = AttendanceMark::query()->count();
        Log::spy();

        $review = review($delivery->mark_id ?? '', ReviewDecision::Verified);

        expect($review)->decision->toBe(ReviewDecision::Verified)->reviewer_id->toBe($this->hr->id)->note->toBeNull()
            ->and(MarkReview::query()->count())->toBe(1)
            ->and(AttendanceMark::query()->count())->toBe($before)
            ->and(AttendanceMark::query()->findOrFail($delivery->mark_id)->marked_at?->toIso8601String())->toBe('2026-11-02T15:00:00+00:00');
        Log::shouldHaveReceived('info')->with('attendance.offline_mark_reviewed', ['mark_id' => $delivery->mark_id, 'employee_id' => $this->ana->id, 'decision' => 'verified', 'corrected' => false]);
    })->with(['with a QR' => true, 'without one' => false]);

    it('keeps an optional note of the review', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');

        expect(review($delivery->mark_id ?? '', ReviewDecision::Verified, 'Lo confirmó su jefa.')->note)->toBe('Lo confirmó su jefa.');
    });

    it('writes the audit trail with ids only, and tells the person with the event', function (): void {
        Event::fake([OfflineMarkReviewed::class]);
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');

        review($delivery->mark_id ?? '', ReviewDecision::Verified, 'Lo confirmó su jefa.');

        $audit = AuditEntry::query()->where('event', 'attendance.offline_mark_reviewed')->sole();
        expect($audit->metadata)->toEqual(['mark_id' => $delivery->mark_id, 'employee_id' => $this->ana->id, 'decision' => 'verified', 'corrected' => false])
            ->and(json_encode(AuditEntry::query()->get(), JSON_THROW_ON_ERROR))->not->toContain('su jefa');
        Event::assertDispatched(OfflineMarkReviewed::class, fn (OfflineMarkReviewed $event): bool => $event->markId === $delivery->mark_id
            && $event->employeeId === $this->ana->id && $event->decision === 'verified');
    });

    it('lets the last decision rule when the mark is reviewed again', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');
        $first = review($delivery->mark_id ?? '', ReviewDecision::Verified);
        $second = review($delivery->mark_id ?? '', ReviewDecision::Verified, 'Otra vez, con su jefa.');

        expect(MarkReview::query()->count())->toBe(2)
            ->and(MarkReview::latestByMark([$delivery->mark_id ?? ''])[$delivery->mark_id ?? '']->id)->toBe($second->id)->not->toBe($first->id);
    });
});

describe('marking it incorrect', function (): void {
    it('corrects the mark to the time RH writes, with the note as its reason; the original stays', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');
        Event::fake([OfflineMarkReviewed::class]);

        $review = review($delivery->mark_id ?? '', ReviewDecision::Incorrect, 'Entró a las 9:20, lo dice la cámara.', '2026-11-02 09:20');

        $correction = AttendanceMark::query()->where('corrects_mark_id', $delivery->mark_id)->sole();
        expect($review)->decision->toBe(ReviewDecision::Incorrect)->note->toBe('Entró a las 9:20, lo dice la cámara.')
            ->and($correction)->method->toBe(MarkMethod::Correction)->reason->toBe('Entró a las 9:20, lo dice la cámara.')->corrected_by->toBe($this->hr->id)
            ->and($correction->marked_at?->toIso8601String())->toBe('2026-11-02T15:20:00+00:00')
            ->and(AttendanceMark::query()->find($delivery->mark_id))->not->toBeNull();
        $audit = AuditEntry::query()->where('event', 'attendance.offline_mark_reviewed')->sole();
        expect($audit->metadata)->toEqual(['mark_id' => $delivery->mark_id, 'employee_id' => $this->ana->id, 'decision' => 'incorrect', 'corrected' => true])
            ->and(json_encode(AuditEntry::query()->get(), JSON_THROW_ON_ERROR))->not->toContain('la cámara')->not->toContain('09:20');
        Event::assertDispatched(OfflineMarkReviewed::class, fn (OfflineMarkReviewed $event): bool => $event->markId === $delivery->mark_id && $event->decision === 'incorrect');
    });

    it('voids the mark when RH writes no time', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');

        review($delivery->mark_id ?? '', ReviewDecision::Incorrect, 'No estuvo en la planta ese día.');

        expect(AttendanceMark::query()->where('corrects_mark_id', $delivery->mark_id)->sole()->marked_at)->toBeNull();
    });

    it('lets RH count the time the person declared when the rule counted another', function (): void {
        // The rule counted 10:00, the person declared 12:30 after 72 hours without a connection.
        $counted = AttendanceMark::factory()->offlineDeclared()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id,
            'type' => MarkType::Start, 'marked_at' => CarbonImmutable::parse('2026-11-02 10:00', 'America/Mexico_City')->utc(),
        ]);
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 12:30', flags: [ReviewFlag::DeclaredAdjusted], mark: $counted);
        expect($delivery->mark_id)->toBe($counted->id);

        review($counted->id, ReviewDecision::Incorrect, 'La hora buena es la que declaró.', '2026-11-02 12:30');

        $effective = AttendanceMark::query()->where('employee_id', $this->ana->id)->where('corrects_mark_id', $counted->id)->sole();
        expect($effective->marked_at?->toIso8601String())->toBe('2026-11-02T18:30:00+00:00');
    });

    it('reopens an answered week, confirmed or disputed, as any correction does', function (WeekStatus $status): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-03 09:00');
        $device = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        $week = WeeklyConfirmation::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-02', 'status' => $status,
            'confirmed_at' => $status === WeekStatus::Confirmed ? now() : null, 'disputed_at' => $status === WeekStatus::Disputed ? now() : null,
            'credential_id' => $device->id, 'reason' => $status === WeekStatus::Disputed ? 'Me faltan horas.' : null,
            'digest' => WeekDigest::of($this->ana->id, WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-02'), 'America/Mexico_City')),
        ]);

        review($delivery->mark_id ?? '', ReviewDecision::Incorrect, 'Entró a las 9:20.', '2026-11-03 09:20');

        expect($week->fresh()?->status)->toBe(WeekStatus::Pending);
    })->with([WeekStatus::Confirmed, WeekStatus::Disputed]);

    it('leaves the week as it was when RH only verifies', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-03 09:00');
        $device = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        $week = WeeklyConfirmation::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-02', 'status' => WeekStatus::Confirmed,
            'confirmed_at' => now(), 'credential_id' => $device->id,
            'digest' => WeekDigest::of($this->ana->id, WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-02'), 'America/Mexico_City')),
        ]);

        review($delivery->mark_id ?? '', ReviewDecision::Verified);

        expect($week->fresh()?->status)->toBe(WeekStatus::Confirmed);
    });

    it('needs a note of 5 characters', function (?string $note): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');

        expect(fn () => review($delivery->mark_id ?? '', ReviewDecision::Incorrect, $note, '2026-11-02 09:20'))->toThrow(InvalidArgumentException::class)
            ->and(MarkReview::query()->count())->toBe(0)
            ->and(AttendanceMark::query()->where('corrects_mark_id', $delivery->mark_id)->count())->toBe(0);
    })->with([null, '   ', 'No.', 'Cuat']);

    it('refuses a time that leaves the day out of order and leaves no review behind', function (): void {
        AttendanceMark::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id,
            'type' => MarkType::Start, 'marked_at' => CarbonImmutable::parse('2026-11-02 09:20', 'America/Mexico_City')->utc(),
        ]);
        $end = OfflineDeliveries::accepted($this->ana, '2026-11-02 18:00', MarkType::End);

        expect(reviewRefusedFor(fn () => review($end->mark_id ?? '', ReviewDecision::Incorrect, 'Salió más temprano.', '2026-11-02 08:00')))->toBe('sequence_invalid')
            ->and(MarkReview::query()->count())->toBe(0)
            ->and(AttendanceMark::query()->where('corrects_mark_id', $end->mark_id)->count())->toBe(0)
            ->and(AuditEntry::query()->where('event', 'attendance.offline_mark_reviewed')->count())->toBe(0);
    });

    it('refuses a time that has not come yet', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');

        expect(reviewRefusedFor(fn () => review($delivery->mark_id ?? '', ReviewDecision::Incorrect, 'Salió más tarde.', '2026-11-11 13:00')))->toBe('future_time')
            ->and(MarkReview::query()->count())->toBe(0);
    });
});

describe('what it reviews', function (): void {
    it('refuses a mark that was corrected since it was opened', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');
        review($delivery->mark_id ?? '', ReviewDecision::Incorrect, 'Entró a las 9:20.', '2026-11-02 09:20');

        expect(reviewRefusedFor(fn () => review($delivery->mark_id ?? '', ReviewDecision::Verified)))->toBe('mark_changed')
            ->and(MarkReview::query()->count())->toBe(1);
    });

    it('refuses a mark that was not made without a connection', function (): void {
        $online = AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id]);

        expect(reviewRefusedFor(fn () => review($online->id, ReviewDecision::Verified)))->toBe('not_offline')
            ->and(MarkReview::query()->count())->toBe(0);
    });

    it('is reviewed only by a member of the company, and only in it', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');
        $outsider = memberOf(Tenant::factory()->create(), TenantRole::Hr);
        workIn($this->tenant);

        expect(fn () => review($delivery->mark_id ?? '', ReviewDecision::Verified, by: User::factory()->create()))->toThrow(InvalidArgumentException::class)
            ->and(fn () => review($delivery->mark_id ?? '', ReviewDecision::Verified, by: $outsider))->toThrow(InvalidArgumentException::class)
            ->and(MarkReview::query()->count())->toBe(0);
    });

    it('does not find the mark of another company', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');
        $other = Tenant::factory()->create();
        workIn($other);

        expect(fn () => resolve(ReviewOfflineMark::class)->handle($delivery->mark_id ?? '', ReviewDecision::Verified, memberOf($other, TenantRole::Hr)->id, null))->toThrow(ModelNotFoundException::class);
        workIn($this->tenant);
        expect(MarkReview::query()->count())->toBe(0);
    });
});

describe('verifying several', function (): void {
    it('writes a review, an audit entry and an event for each one', function (): void {
        Event::fake([OfflineMarkReviewed::class]);
        $marks = array_map(fn (string $day) => OfflineDeliveries::accepted($this->ana, "2026-11-0{$day} 09:00")->mark_id ?? '', ['2', '3', '4']);

        expect(verifyMany($marks))->toBe(['verified' => 3, 'skipped' => 0])
            ->and(MarkReview::query()->count())->toBe(3)
            ->and(AuditEntry::query()->where('event', 'attendance.offline_mark_reviewed')->count())->toBe(3);
        Event::assertDispatchedTimes(OfflineMarkReviewed::class, 3);
    });

    it('skips what is already reviewed, what changed, and what is not of the company', function (): void {
        $fresh = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00')->mark_id ?? '';
        $reviewed = OfflineDeliveries::accepted($this->ana, '2026-11-03 09:00')->mark_id ?? '';
        $corrected = OfflineDeliveries::accepted($this->ana, '2026-11-04 09:00')->mark_id ?? '';
        review($reviewed, ReviewDecision::Verified);
        review($corrected, ReviewDecision::Incorrect, 'No estuvo ese día.');
        $online = AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id])->id;
        $alien = (string) fake()->uuid();
        $other = Tenant::factory()->create();
        workIn($other);
        $foreign = OfflineDeliveries::accepted(EmployeeFactory::new()->create(['tenant_id' => $other->id, 'work_center_id' => WorkCenter::factory()->for($other)->create()->id]), '2026-11-02 09:00')->mark_id ?? '';
        workIn($this->tenant);

        expect(verifyMany([$fresh, $reviewed, $corrected, $online, $alien, $foreign]))->toBe(['verified' => 1, 'skipped' => 5])
            ->and(MarkReview::query()->where('mark_id', $fresh)->count())->toBe(1);
    });

    it('does nothing with an empty list', function (): void {
        expect(verifyMany([]))->toBe(['verified' => 0, 'skipped' => 0])
            ->and(MarkReview::query()->count())->toBe(0);
    });

    it('takes at most 25 marks', function (): void {
        expect(fn () => verifyMany(array_map(fn (int $n): string => (string) fake()->uuid(), range(1, 26))))->toThrow(InvalidArgumentException::class);
    });
});
