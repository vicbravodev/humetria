<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Models\User;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A start of the day made at a center with the QR and inside the geofence, now.
 *
 * @extends Factory<AttendanceMark>
 */
final class AttendanceMarkFactory extends Factory
{
    protected $model = AttendanceMark::class;

    /**
     * @return array<model-property<AttendanceMark>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id'], 'work_center_id' => $attributes['work_center_id']]),
            'type' => MarkType::Start,
            'marked_at' => now(),
            'method' => MarkMethod::QrAndGeofence,
        ];
    }

    /** RH's correction of `$original`, to the same time unless a state says otherwise. */
    public function correcting(AttendanceMark $original): self
    {
        return $this->state([
            'tenant_id' => $original->tenant_id,
            'employee_id' => $original->employee_id,
            'work_center_id' => $original->work_center_id,
            'type' => $original->type,
            'marked_at' => $original->marked_at,
            'method' => MarkMethod::Correction,
            'corrects_mark_id' => $original->id,
            'corrected_by' => User::factory(),
            'reason' => 'La persona olvidó marcar a tiempo.',
        ]);
    }
}
