<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Leave\Enums\RowKind;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeavePolicyRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A row of a vacation policy: the days of a range of years of service, never under the law (criterio C-13), or days that
 * come every year with a name of their own (criterio C-14).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $leave_policy_id
 * @property RowKind $kind
 * @property string|null $label
 * @property int|null $year_from
 * @property int|null $year_to
 * @property int $days
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'leave_policy_id', 'kind', 'label', 'year_from', 'year_to', 'days'])]
#[UseFactory(LeavePolicyRowFactory::class)]
final class LeavePolicyRow extends Model
{
    /** @use HasFactory<LeavePolicyRowFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => RowKind::class, 'year_from' => 'integer', 'year_to' => 'integer', 'days' => 'integer'];
    }
}
