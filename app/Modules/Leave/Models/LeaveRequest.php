<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\MatchesManyIds;
use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What a person asks for: vacations (no type) or a leave that uses no balance (criterio C-19). Its working days are
 * counted when it is made and never again (criterio C-04). For vacations it keeps whether the person had the days then
 * and where they would come from; the ledger is only written when it is approved. The note, the type and the reason of a
 * rejection never reach the logs.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string|null $leave_type_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $working_days
 * @property RequestStatus $status
 * @property string|null $note
 * @property bool|null $had_balance
 * @property list<array{entitlement_id: string, tranche: string, days: int}> $allocation
 * @property int $short_by
 * @property string|null $approver_employee_id
 * @property CarbonImmutable|null $escalated_at
 * @property EscalationReason|null $escalation_reason
 * @property string|null $decided_by_employee_id
 * @property int|null $decided_by_user_id
 * @property CarbonImmutable|null $decided_at
 * @property string|null $rejection_reason
 * @property int|null $recorded_by
 * @property CarbonImmutable $requested_on
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'leave_type_id', 'starts_on', 'ends_on', 'working_days', 'status', 'note', 'had_balance', 'allocation', 'short_by', 'approver_employee_id', 'escalated_at', 'escalation_reason', 'decided_by_employee_id', 'decided_by_user_id', 'decided_at', 'rejection_reason', 'recorded_by', 'requested_on', 'cancelled_at'])]
#[UseFactory(LeaveRequestFactory::class)]
final class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, MatchesManyIds;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'requested_on' => 'immutable_date',
            'working_days' => 'integer',
            'short_by' => 'integer',
            'status' => RequestStatus::class,
            'had_balance' => 'boolean',
            'allocation' => 'array',
            'escalated_at' => 'immutable_datetime',
            'escalation_reason' => EscalationReason::class,
            'decided_by_user_id' => 'integer',
            'decided_at' => 'immutable_datetime',
            'recorded_by' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
