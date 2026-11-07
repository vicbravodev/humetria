<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Models\AttendanceSchedule;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A day shift on site, eight hours Monday to Friday.
 *
 * @extends Factory<AttendanceSchedule>
 */
final class AttendanceScheduleFactory extends Factory
{
    protected $model = AttendanceSchedule::class;

    /**
     * @return array<model-property<AttendanceSchedule>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'shift_type' => ShiftType::Day,
            'work_mode' => WorkMode::Onsite,
            'minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0],
        ];
    }
}
