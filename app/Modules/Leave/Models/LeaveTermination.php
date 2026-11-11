<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeaveTerminationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What a person was owed, in days, the day the relationship ended (criterio C-07): what was left of their periods, less
 * what they had been given in advance, and the part of the year in course. Never an amount of money. Settling it takes the
 * days out of the ledger; if the person comes back before that, it is voided.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property CarbonImmutable $terminated_on
 * @property int $pending_days
 * @property float $proportional_days
 * @property CarbonImmutable|null $settled_at
 * @property int|null $settled_by
 * @property CarbonImmutable|null $voided_at
 */
#[Fillable(['tenant_id', 'employee_id', 'terminated_on', 'pending_days', 'proportional_days', 'settled_at', 'settled_by', 'voided_at'])]
#[UseFactory(LeaveTerminationFactory::class)]
final class LeaveTermination extends Model
{
    /** @use HasFactory<LeaveTerminationFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terminated_on' => 'immutable_date',
            'pending_days' => 'integer',
            'proportional_days' => 'float',
            'settled_at' => 'immutable_datetime',
            'settled_by' => 'integer',
            'voided_at' => 'immutable_datetime',
        ];
    }
}
