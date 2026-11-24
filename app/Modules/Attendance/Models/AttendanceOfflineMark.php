<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OfflineRejection;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Enums\SignerType;
use App\Modules\Attendance\Enums\TimeBasis;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendanceOfflineMarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A mark delivered after being made without a connection (criterios 3.3.1 and 7.1), with a valid ticket and signature:
 * accepted (with the mark it created) or rejected with its reason. One row per ticket. Insert-only: a Postgres trigger
 * rejects UPDATE, DELETE and TRUNCATE (the demo company's rows may be deleted). The person's note is for Recursos Humanos
 * only and never goes to a log.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $outcome
 * @property OfflineRejection|null $reason
 * @property string|null $mark_id
 * @property string $work_center_id
 * @property string|null $away_center_id Another center of the company where the QR proved the person stood.
 * @property MarkType $mark_type
 * @property TimeBasis|null $time_basis
 * @property CarbonImmutable $declared_at
 * @property CarbonImmutable|null $counted_at
 * @property CarbonImmutable|null $window_start
 * @property string $ticket_nonce
 * @property CarbonImmutable $ticket_issued_at
 * @property CarbonImmutable $received_at
 * @property Collection<int, ReviewFlag> $review_flags
 * @property CarbonImmutable|null $device_clock_at The phone's own clock when delivering: a hint for Recursos Humanos, never evidence.
 * @property SignerType $signer_type
 * @property string $signer_id
 * @property int $seq
 * @property string|null $justification
 * @property int $agreement_version
 * @property string $challenge_hash
 * @property string $assertion_hash
 * @property CarbonImmutable $created_at
 * @property-read AttendanceMark|null $mark
 */
#[Fillable(['tenant_id', 'employee_id', 'outcome', 'reason', 'mark_id', 'work_center_id', 'away_center_id', 'mark_type', 'time_basis', 'declared_at', 'counted_at', 'window_start', 'ticket_nonce', 'ticket_issued_at', 'received_at', 'review_flags', 'device_clock_at', 'signer_type', 'signer_id', 'seq', 'justification', 'agreement_version', 'challenge_hash', 'assertion_hash'])]
#[Table('attendance_offline_marks')]
#[UseFactory(AttendanceOfflineMarkFactory::class)]
final class AttendanceOfflineMark extends Model
{
    /** @use HasFactory<AttendanceOfflineMarkFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public $timestamps = false;

    /** To the microsecond: the received and counted times keep their order. */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'reason' => OfflineRejection::class,
            'mark_type' => MarkType::class,
            'time_basis' => TimeBasis::class,
            'signer_type' => SignerType::class,
            'review_flags' => AsEnumCollection::of(ReviewFlag::class),
            'declared_at' => 'immutable_datetime',
            'counted_at' => 'immutable_datetime',
            'window_start' => 'immutable_datetime',
            'ticket_issued_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'device_clock_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'seq' => 'integer',
            'agreement_version' => 'integer',
        ];
    }

    /** @return BelongsTo<AttendanceMark, $this> */
    public function mark(): BelongsTo
    {
        return $this->belongsTo(AttendanceMark::class, 'mark_id');
    }
}
