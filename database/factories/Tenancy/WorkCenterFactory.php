<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkCenter>
 */
final class WorkCenterFactory extends Factory
{
    /**
     * @return array<model-property<WorkCenter>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Planta '.fake()->unique()->city(),
            'street' => fake()->streetName(),
            'exterior_number' => (string) fake()->numberBetween(1, 9999),
            'interior_number' => null,
            'neighborhood' => 'Centro',
            'postal_code' => fake()->numerify('#####'),
            'municipality' => fake()->city(),
            'state' => fake()->randomElement(MexicanState::cases()),
            'main_activity' => 'Manufactura de autopartes',
        ];
    }

    public function archived(): self
    {
        return $this->state(['archived_at' => now()]);
    }
}
