<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Models\TeleworkProfile;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A center that declared a month ago that nobody works away from it more than 40 % of their time.
 *
 * @extends Factory<TeleworkProfile>
 */
final class TeleworkProfileFactory extends Factory
{
    protected $model = TeleworkProfile::class;

    /**
     * @return array<model-property<TeleworkProfile>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'has_teleworkers' => false,
            'declared_by' => User::factory(),
            'declared_on' => now()->subMonth()->toDateString(),
        ];
    }

    /** Someone works away from the center more than 40 % of their time: the route applies. */
    public function teleworks(): self
    {
        return $this->state(['has_teleworkers' => true]);
    }
}
