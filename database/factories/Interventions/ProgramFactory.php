<?php

declare(strict_types=1);

namespace Database\Factories\Interventions;

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Models\Program;
use Database\Factories\Campaigns\CampaignFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The Programa of a closed evaluation, drafted by the system, with Medio as its highest level and nobody responsible yet.
 *
 * @extends Factory<Program>
 */
final class ProgramFactory extends Factory
{
    protected $model = Program::class;

    /**
     * @return array<model-property<Program>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'campaign_id' => fn (array $attributes) => CampaignFactory::new()->closed()->state(['tenant_id' => $attributes['tenant_id']]),
            'highest_level' => RiskLevel::Medium,
            'responsible_name' => null,
            'responsible_user_id' => null,
            'created_by' => null,
        ];
    }
}
