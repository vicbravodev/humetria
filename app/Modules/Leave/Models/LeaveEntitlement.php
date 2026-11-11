<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\MatchesManyIds;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeaveEntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The vacation period one year of service gives a person, with the name and the terms of the policy it was granted with
 * (criterio C-15) and the dates of its window (criterios C-09 and C-11). One per year of service; it is never changed: what
 * is left of it is the sum of its entries in the ledger.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property int $years_of_service
 * @property CarbonImmutable $anniversary_on
 * @property string|null $leave_policy_id
 * @property string $policy_name
 * @property int $premium_percent
 * @property int $statutory_days
 * @property int $extra_days
 * @property CarbonImmutable $granted_on
 * @property CarbonImmutable $enjoy_by
 * @property CarbonImmutable $prescribes_on
 * @property CarbonImmutable|null $extra_expires_on
 * @property string $source
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'employee_id', 'years_of_service', 'anniversary_on', 'leave_policy_id', 'policy_name', 'premium_percent', 'statutory_days', 'extra_days', 'granted_on', 'enjoy_by', 'prescribes_on', 'extra_expires_on', 'source'])]
#[UseFactory(LeaveEntitlementFactory::class)]
final class LeaveEntitlement extends Model
{
    /** @use HasFactory<LeaveEntitlementFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, MatchesManyIds;

    public const null UPDATED_AT = null;

    /** Granted by the daily command on the anniversary. */
    public const string ANNIVERSARY = 'anniversary';

    /** Loaded by the company as an opening balance (criterio C-21). */
    public const string OPENING = 'opening';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'years_of_service' => 'integer',
            'premium_percent' => 'integer',
            'statutory_days' => 'integer',
            'extra_days' => 'integer',
            'anniversary_on' => 'immutable_date',
            'granted_on' => 'immutable_date',
            'enjoy_by' => 'immutable_date',
            'prescribes_on' => 'immutable_date',
            'extra_expires_on' => 'immutable_date',
        ];
    }
}
