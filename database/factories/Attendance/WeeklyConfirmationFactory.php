<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending week of a person: the one of Monday 2 November 2026.
 *
 * @extends Factory<WeeklyConfirmation>
 */
final class WeeklyConfirmationFactory extends Factory
{
    protected $model = WeeklyConfirmation::class;

    /**
     * @return array<model-property<WeeklyConfirmation>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'week_start' => '2026-11-02',
            'status' => WeekStatus::Pending,
        ];
    }
}
