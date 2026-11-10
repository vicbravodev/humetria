<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeavePolicy;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A policy with the premium of the law and no rows: it gives the days of the law, Monday to Friday.
 *
 * @extends Factory<LeavePolicy>
 */
final class LeavePolicyFactory extends Factory
{
    protected $model = LeavePolicy::class;

    /**
     * @return array<model-property<LeavePolicy>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'name' => 'Política '.fake()->unique()->numberBetween(1, 99999),
            'premium_percent' => 25,
            'work_week' => WorkWeek::MonFri,
        ];
    }

    public function archived(): self
    {
        return $this->state(['archived_at' => now()]);
    }
}
