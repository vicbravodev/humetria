<?php

declare(strict_types=1);

namespace Database\Factories\Organization;

use App\Modules\Organization\Models\Area;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
final class AreaFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as the employees' factory does. */
    protected $model = Area::class;

    /**
     * @return array<model-property<Area>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'name' => 'Área '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
