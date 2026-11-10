<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Leave\Enums\WorkWeek;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeavePolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vacation policy of the company: it can only improve the law (criterio C-13). Its premium is a percentage, never an
 * amount (criterio C-08). The «Ley» policy is not a row: it is the absence of one.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property int $premium_percent
 * @property WorkWeek $work_week
 * @property bool $allows_advance
 * @property int|null $extra_expires_months
 * @property int|null $extra_carryover_cap_days
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'name', 'premium_percent', 'work_week', 'allows_advance', 'extra_expires_months', 'extra_carryover_cap_days', 'archived_at'])]
#[UseFactory(LeavePolicyFactory::class)]
final class LeavePolicy extends Model
{
    /** @use HasFactory<LeavePolicyFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return HasMany<LeavePolicyRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(LeavePolicyRow::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'premium_percent' => 'integer',
            'work_week' => WorkWeek::class,
            'allows_advance' => 'boolean',
            'extra_expires_months' => 'integer',
            'extra_carryover_cap_days' => 'integer',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
