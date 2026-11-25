<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\OfflineRejection;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Enums\SignerType;
use App\Modules\Attendance\Enums\TimeBasis;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceOfflineMark;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Attendance\AttendanceMarkFactory;
use Database\Factories\Attendance\AttendanceOfflineMarkFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The deliveries of marks made without a connection (criterios 3.3.1 and 7.1): what the server received, accepted or
 * rejected, kept as it arrived. The database itself keeps them honest, even from code that skips the enums: each
 * statement that must fail runs in its own savepoint, because the test is one transaction.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/** Inserts a copy of `$base` with SQL, bypassing the casts, with a new id and ticket unless the overrides say otherwise. */
function insertDeliveryLike(AttendanceOfflineMark $base, array $overrides): void
{
    DB::table('attendance_offline_marks')->insert([
        ...Arr::except($base->getRawOriginal(), ['id', 'ticket_nonce', 'mark_id']),
        'id' => Str::uuid()->toString(),
        'ticket_nonce' => Str::lower(Str::random(32)),
        ...$overrides,
    ]);
}

/** The error the database gave to an insert, or null when it went through. */
function deliveryRefusal(Closure $insert): ?string
{
    try {
        DB::transaction($insert);
    } catch (QueryException $e) {
        return $e->getMessage();
    }

    return null;
}

it('keeps a rejected delivery and an accepted one, with what each one carries', function (): void {
    $rejected = AttendanceOfflineMarkFactory::new()->rejected(OfflineRejection::WrongCenter)->flagged(ReviewFlag::NoPosition, ReviewFlag::WideInterval)->createOne(['tenant_id' => $this->tenant->id])->refresh();
    $accepted = AttendanceOfflineMarkFactory::new()->accepted()->qrWindow()->deviceKey()->createOne(['tenant_id' => $this->tenant->id])->refresh();

    expect($rejected->reason)->toBe(OfflineRejection::WrongCenter)->and($rejected->mark_id)->toBeNull()
        ->and($rejected->review_flags->all())->toBe([ReviewFlag::NoPosition, ReviewFlag::WideInterval])
        ->and($accepted->time_basis)->toBe(TimeBasis::QrWindow)->and($accepted->signer_type)->toBe(SignerType::DeviceKey)
        ->and($accepted->window_start)->not->toBeNull()
        ->and($accepted->mark)->toBeInstanceOf(AttendanceMark::class)
        ->and($accepted->mark->method)->toBe(MarkMethod::OfflineQr)
        ->and($accepted->mark->marked_at?->equalTo($accepted->counted_at))->toBeTrue();
});

it('never edits nor deletes a delivery, not even with SQL', function (string $sql): void {
    $delivery = AttendanceOfflineMarkFactory::new()->createOne(['tenant_id' => $this->tenant->id]);

    expect(deliveryRefusal(fn () => DB::statement(str_replace(':id', "'{$delivery->id}'", $sql))))->toContain('attendance_offline_marks is append-only');
})->with([
    'update' => ['update attendance_offline_marks set seq = 9 where id = :id'],
    'delete' => ['delete from attendance_offline_marks where id = :id'],
    'truncate' => ['truncate attendance_offline_marks cascade'],
]);

it('rejects a row that does not tell its story', function (array $overrides, string $constraint): void {
    $base = AttendanceOfflineMarkFactory::new()->createOne(['tenant_id' => $this->tenant->id]);

    expect(deliveryRefusal(fn () => insertDeliveryLike($base, $overrides)))->toContain($constraint);
})->with([
    'accepted without its mark' => [['outcome' => 'accepted', 'reason' => null, 'time_basis' => 'declared_interval', 'counted_at' => now()->subMinutes(15)->toIso8601String()], 'attendance_offline_marks_accepted_complete'],
    'rejected without a reason' => [['reason' => null], 'attendance_offline_marks_rejected_has_reason'],
    'a reason that is only answered' => [['reason' => 'signature_invalid'], 'attendance_offline_marks_reason_known'],
    'a signer that does not exist' => [['signer_type' => 'pin'], 'attendance_offline_marks_signer_known'],
    'a type of mark that does not exist' => [['mark_type' => 'lunch'], 'attendance_offline_marks_type_known'],
    'a basis that does not exist' => [['time_basis' => 'guess'], 'attendance_offline_marks_basis_known'],
    'a negative sequence' => [['seq' => -1], 'attendance_offline_marks_seq_not_negative'],
    'a note of 501 characters' => [['justification' => str_repeat('a', 501)], 'value too long'],
    'a flag nobody knows' => [['review_flags' => '["bogus"]'], 'attendance_offline_marks_flags_known'],
    'flags that are not a list' => [['review_flags' => '{"wide_interval": true}'], 'attendance_offline_marks_flags_known'],
    'no signer' => [['signer_id' => null], 'signer_id'],
]);

it('rejects a window basis without the start of the window, and a counted time outside the ticket and the receipt', function (): void {
    $base = AttendanceOfflineMarkFactory::new()->accepted()->createOne(['tenant_id' => $this->tenant->id]);
    $fresh = fn (array $overrides): Closure => fn () => insertDeliveryLike($base, [
        'mark_id' => AttendanceMarkFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'employee_id' => $base->employee_id, 'work_center_id' => $base->work_center_id])->id,
        ...$overrides,
    ]);

    expect(deliveryRefusal($fresh(['time_basis' => 'qr_window', 'window_start' => null])))->toContain('attendance_offline_marks_window_has_start')
        ->and(deliveryRefusal($fresh(['counted_at' => now()->subDay()->toIso8601String()])))->toContain('attendance_offline_marks_counted_between')
        ->and(deliveryRefusal($fresh(['counted_at' => now()->addDay()->toIso8601String()])))->toContain('attendance_offline_marks_counted_between')
        ->and(deliveryRefusal($fresh([])))->toBeNull();
});

it('answers for a mark in a single delivery', function (): void {
    $delivery = AttendanceOfflineMarkFactory::new()->accepted()->createOne(['tenant_id' => $this->tenant->id]);

    expect(deliveryRefusal(fn () => insertDeliveryLike($delivery, ['mark_id' => $delivery->mark_id])))->toContain('attendance_offline_marks_mark_id_unique');
});

it('keeps a ticket for a single delivery in a company, and lets another company have the same value', function (): void {
    $delivery = AttendanceOfflineMarkFactory::new()->createOne(['tenant_id' => $this->tenant->id]);
    $other = AttendanceOfflineMarkFactory::new()->createOne();

    expect(deliveryRefusal(fn () => insertDeliveryLike($delivery, ['ticket_nonce' => $delivery->ticket_nonce])))->toContain('attendance_offline_marks_tenant_id_ticket_nonce_unique')
        ->and(deliveryRefusal(fn () => insertDeliveryLike($other, ['ticket_nonce' => $delivery->ticket_nonce])))->toBeNull();
});
