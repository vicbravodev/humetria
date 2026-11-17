<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Modules\Attendance\Enums\CenterMode;
use App\Modules\Attendance\Models\AttendanceCenterMode;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A work center that marks in the field.
 *
 * @extends Factory<AttendanceCenterMode>
 */
final class AttendanceCenterModeFactory extends Factory
{
    protected $model = AttendanceCenterMode::class;

    /**
     * @return array<model-property<AttendanceCenterMode>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->createOne(['tenant_id' => $attributes['tenant_id']])->id,
            'mode' => CenterMode::Field,
        ];
    }
}
