<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendanceZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A circle (center and radius in meters) inside which a person of a work center may mark (criterio 3.4).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property string $name
 * @property float $latitude
 * @property float $longitude
 * @property int $radius_m
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'name', 'latitude', 'longitude', 'radius_m'])]
#[UseFactory(AttendanceZoneFactory::class)]
final class AttendanceZone extends Model
{
    /** @use HasFactory<AttendanceZoneFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'radius_m' => 'integer'];
    }
}
