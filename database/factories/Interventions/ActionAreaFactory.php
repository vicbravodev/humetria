<?php

declare(strict_types=1);

namespace Database\Factories\Interventions;

use App\Modules\Interventions\Models\ActionArea;
use Database\Factories\Organization\AreaFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An area an action is for.
 *
 * @extends Factory<ActionArea>
 */
final class ActionAreaFactory extends Factory
{
    protected $model = ActionArea::class;

    /**
     * @return array<model-property<ActionArea>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'action_id' => fn (array $attributes) => ProgramActionFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'area_id' => fn (array $attributes) => AreaFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
        ];
    }
}
