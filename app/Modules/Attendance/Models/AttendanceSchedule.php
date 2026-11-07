<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendanceScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The agreed weekly schedule of a person (criterio 2.6): their kind of shift, where they work and the minutes agreed for
 * each ISO weekday (1 Monday to 7 Sunday). One per person.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property ShiftType $shift_type
 * @property WorkMode $work_mode
 * @property array<int, int> $minutes_by_weekday
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'shift_type', 'work_mode', 'minutes_by_weekday'])]
#[UseFactory(AttendanceScheduleFactory::class)]
final class AttendanceSchedule extends Model
{
    /** @use HasFactory<AttendanceScheduleFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['shift_type' => ShiftType::class, 'work_mode' => WorkMode::class, 'minutes_by_weekday' => 'array'];
    }
}
