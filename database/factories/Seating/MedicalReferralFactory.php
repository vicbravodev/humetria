<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Models\User;
use App\Modules\Seating\Models\MedicalReferral;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A referral to medical attention recorded last week.
 *
 * @extends Factory<MedicalReferral>
 */
final class MedicalReferralFactory extends Factory
{
    protected $model = MedicalReferral::class;

    /**
     * @return array<model-property<MedicalReferral>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'referred' => true,
            'referred_on' => now()->subWeek()->toDateString(),
            'note' => null,
            'created_by' => User::factory(),
        ];
    }
}
