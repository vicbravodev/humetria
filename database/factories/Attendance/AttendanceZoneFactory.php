<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Modules\Attendance\Models\AttendanceZone;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A 50 m zone in Mexico City.
 *
 * @extends Factory<AttendanceZone>
 */
final class AttendanceZoneFactory extends Factory
{
    protected $model = AttendanceZone::class;

    /**
     * @return array<model-property<AttendanceZone>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->createOne(['tenant_id' => $attributes['tenant_id']])->id,
            'name' => fake()->words(2, true),
            'latitude' => 19.4326,
            'longitude' => -99.1332,
            'radius_m' => 50,
        ];
    }
}
