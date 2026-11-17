<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Attendance\Enums\CenterMode;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendanceCenterModeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * How the people of one work center mark (criterio 4.5). At most one per center; a center without one goes by the
 * company's policy.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property CenterMode $mode
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'mode'])]
#[UseFactory(AttendanceCenterModeFactory::class)]
final class AttendanceCenterMode extends Model
{
    /** @use HasFactory<AttendanceCenterModeFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /** Whether the people of `$workCenterId` mark in the field. */
    public static function isField(string $workCenterId): bool
    {
        return self::query()->where('work_center_id', $workCenterId)->where('mode', CenterMode::Field)->exists();
    }

    /**
     * The ids of the company's centers that mark in the field.
     *
     * @return array<string, true> by center id
     */
    public static function fieldCenters(): array
    {
        $field = [];
        foreach (self::query()->where('mode', CenterMode::Field)->get(['work_center_id']) as $mode) {
            $field[$mode->work_center_id] = true;
        }

        return $field;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['mode' => CenterMode::class];
    }
}
