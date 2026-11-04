<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Complaints\ComplaintAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * That a handler may open a complaint: the only thing that lets anybody read a case. It keeps days and not hours, so that
 * the assignment made at receipt does not tell the hour the complaint came. It ends, with a reason; it is never deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $complaint_id
 * @property int $user_id
 * @property int|null $assigned_by
 * @property CarbonImmutable $assigned_on
 * @property CarbonImmutable|null $ended_on
 * @property string|null $end_reason
 * @property int|null $notified_position
 */
#[Fillable(['tenant_id', 'complaint_id', 'user_id', 'assigned_by', 'assigned_on', 'ended_on', 'end_reason', 'notified_position'])]
#[WithoutTimestamps]
#[UseFactory(ComplaintAssignmentFactory::class)]
final class ComplaintAssignment extends Model
{
    /** @use HasFactory<ComplaintAssignmentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['assigned_on' => 'immutable_date', 'ended_on' => 'immutable_date', 'notified_position' => 'integer'];
    }
}
