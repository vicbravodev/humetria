<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Models\SafetyCommittee;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A committee constituted a month ago, without act PDF.
 *
 * @extends Factory<SafetyCommittee>
 */
final class SafetyCommitteeFactory extends Factory
{
    protected $model = SafetyCommittee::class;

    /**
     * @return array<model-property<SafetyCommittee>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'constituted_on' => now()->subMonth()->toDateString(),
            'act_path' => null,
            'created_by' => User::factory(),
        ];
    }
}
