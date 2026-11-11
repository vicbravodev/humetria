<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Leave\Enums\WorkWeek;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeaveSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A company that uses Vacaciones: since which day its periods are granted (criterio C-21) and the working week of the
 * «Ley» policy (criterio C-04). One per company; a company without the row does not use the tool.
 *
 * @property string $id
 * @property string $tenant_id
 * @property CarbonImmutable $tracking_since
 * @property WorkWeek $statutory_work_week
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'tracking_since', 'statutory_work_week'])]
#[UseFactory(LeaveSettingFactory::class)]
final class LeaveSetting extends Model
{
    /** @use HasFactory<LeaveSettingFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tracking_since' => 'immutable_date', 'statutory_work_week' => WorkWeek::class];
    }
}
