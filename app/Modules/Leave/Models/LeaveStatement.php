<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeaveStatementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The annual statement of LFT art. 81 (criterio C-10): one per period granted on its anniversary. What it says (seniority,
 * days, when to enjoy them, premium in percent) is read from the period; this row only keeps when it was issued and when
 * the person confirmed they received it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $leave_entitlement_id
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $acknowledged_at
 */
#[Fillable(['tenant_id', 'employee_id', 'leave_entitlement_id', 'issued_at', 'acknowledged_at'])]
#[UseFactory(LeaveStatementFactory::class)]
final class LeaveStatement extends Model
{
    /** @use HasFactory<LeaveStatementFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime'];
    }
}
