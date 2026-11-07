<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendanceMarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One mark of a person's working day, stamped by the server (criterio 3.3). Append-only (criterio 5.4): a Postgres trigger
 * rejects UPDATE, DELETE and TRUNCATE. A correction is a new mark that points to the one it replaces, with who and why; a
 * correction without a time voids its original.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $work_center_id
 * @property MarkType $type
 * @property CarbonImmutable|null $marked_at
 * @property MarkMethod $method
 * @property string|null $zone_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property float|null $accuracy_m
 * @property float|null $distance_m
 * @property string|null $device_id
 * @property string|null $corrects_mark_id
 * @property int|null $corrected_by
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'employee_id', 'work_center_id', 'type', 'marked_at', 'method', 'zone_id', 'latitude', 'longitude', 'accuracy_m', 'distance_m', 'device_id', 'corrects_mark_id', 'corrected_by', 'reason'])]
#[UseFactory(AttendanceMarkFactory::class)]
final class AttendanceMark extends Model
{
    /** @use HasFactory<AttendanceMarkFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MarkType::class,
            'method' => MarkMethod::class,
            'marked_at' => 'immutable_datetime',
            'accuracy_m' => 'float',
            'distance_m' => 'float',
            'corrected_by' => 'integer',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }
}
