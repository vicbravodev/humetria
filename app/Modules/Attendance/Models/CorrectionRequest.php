<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\CorrectionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A person's request to correct their record: a mark of theirs (`mark_id`) or one they forgot, the time they propose (UTC),
 * the type and why. RH resolves it once: approved, with the correction mark it made, or rejected with a note.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string|null $mark_id
 * @property MarkType $type
 * @property CarbonImmutable $proposed_at
 * @property string $reason
 * @property string|null $device_id
 * @property CorrectionStatus $status
 * @property int|null $resolved_by
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $note
 * @property string|null $resulting_mark_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'mark_id', 'type', 'proposed_at', 'reason', 'device_id', 'status', 'resolved_by', 'resolved_at', 'note', 'resulting_mark_id'])]
#[UseFactory(CorrectionRequestFactory::class)]
final class CorrectionRequest extends Model
{
    /** @use HasFactory<CorrectionRequestFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    protected $table = 'attendance_correction_requests';

    protected $attributes = ['status' => 'pending'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MarkType::class,
            'status' => CorrectionStatus::class,
            'proposed_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'resolved_by' => 'integer',
        ];
    }
}
