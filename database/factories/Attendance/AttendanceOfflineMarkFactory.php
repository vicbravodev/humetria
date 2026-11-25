<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OfflineRejection;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Enums\SignerType;
use App\Modules\Attendance\Enums\TimeBasis;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceOfflineMark;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A delivery that was rejected for a sequence conflict: the ticket was issued 30 minutes before the receipt and the person
 * declared a time in between, signing with a passkey.
 *
 * @extends Factory<AttendanceOfflineMark>
 */
final class AttendanceOfflineMarkFactory extends Factory
{
    protected $model = AttendanceOfflineMark::class;

    /**
     * @return array<model-property<AttendanceOfflineMark>, mixed>
     */
    public function definition(): array
    {
        $receivedAt = CarbonImmutable::now();

        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id'], 'work_center_id' => $attributes['work_center_id']]),
            'outcome' => 'rejected',
            'reason' => OfflineRejection::SequenceConflict,
            'mark_type' => MarkType::Start,
            'declared_at' => $receivedAt->subMinutes(10)->startOfSecond(),
            'ticket_nonce' => Str::lower(Str::random(32)),
            'ticket_issued_at' => $receivedAt->subMinutes(30)->startOfSecond(),
            'received_at' => $receivedAt,
            'review_flags' => [],
            'signer_type' => SignerType::Passkey,
            'signer_id' => Str::uuid()->toString(),
            'seq' => 0,
            'agreement_version' => 1,
            'challenge_hash' => hash('sha256', Str::random(16)),
            'assertion_hash' => hash('sha256', Str::random(16)),
        ];
    }

    /** Accepted: with the mark it created (an `offline_declared` one unless the state says a QR bounded it), counted at the declared time. */
    public function accepted(?AttendanceMark $mark = null): self
    {
        // The mark is made when the row is, from the final attributes: `qrWindow()` may come before or after this state.
        return $this->state(fn (array $attributes): array => [
            'outcome' => 'accepted',
            'reason' => null,
            'time_basis' => $attributes['time_basis'] ?? TimeBasis::DeclaredInterval,
            'counted_at' => fn (array $final) => $final['declared_at'],
            'mark_id' => fn (array $final): string => $mark instanceof AttendanceMark ? $mark->id : AttendanceMarkFactory::new()->createOne([
                'tenant_id' => $final['tenant_id'],
                'work_center_id' => $final['work_center_id'],
                'employee_id' => $final['employee_id'],
                'type' => $final['mark_type'],
                'marked_at' => $final['declared_at'],
                'method' => $final['time_basis'] instanceof TimeBasis ? $final['time_basis']->method() : TimeBasis::DeclaredInterval->method(),
            ])->id,
        ]);
    }

    /** A QR proved the mark was not before its window, which began 5 minutes before the declared time (10 minutes ago). */
    public function qrWindow(): self
    {
        return $this->state([
            'time_basis' => TimeBasis::QrWindow,
            'window_start' => CarbonImmutable::now()->subMinutes(15)->startOfSecond(),
        ]);
    }

    public function rejected(OfflineRejection $reason): self
    {
        return $this->state(['outcome' => 'rejected', 'reason' => $reason, 'mark_id' => null, 'time_basis' => null, 'counted_at' => null]);
    }

    public function flagged(ReviewFlag ...$flags): self
    {
        return $this->state(['review_flags' => $flags]);
    }

    public function deviceKey(): self
    {
        return $this->state(['signer_type' => SignerType::DeviceKey]);
    }
}
