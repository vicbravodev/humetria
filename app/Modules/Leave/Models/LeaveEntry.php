<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\MatchesManyIds;
use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Enums\Tranche;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeaveEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of the ledger of vacation days: days that enter (positive) or leave (negative) a tranche of a period.
 * Append-only (criterio C-12): a Postgres trigger rejects UPDATE, DELETE and TRUNCATE, so a correction is always a new
 * entry. Without a period, the days were given in advance (criterio C-17).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string|null $leave_entitlement_id
 * @property EntryKind $kind
 * @property Tranche $tranche
 * @property int $days
 * @property string|null $leave_request_id
 * @property int|null $recorded_by
 * @property int|null $authorized_by
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'employee_id', 'leave_entitlement_id', 'kind', 'tranche', 'days', 'leave_request_id', 'recorded_by', 'authorized_by', 'note'])]
#[UseFactory(LeaveEntryFactory::class)]
final class LeaveEntry extends Model
{
    /** @use HasFactory<LeaveEntryFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, MatchesManyIds;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => EntryKind::class, 'tranche' => Tranche::class, 'days' => 'integer', 'recorded_by' => 'integer', 'authorized_by' => 'integer'];
    }
}
