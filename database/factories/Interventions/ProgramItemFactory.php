<?php

declare(strict_types=1);

namespace Database\Factories\Interventions;

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Enums\ItemOrigin;
use App\Modules\Interventions\Enums\ItemScope;
use App\Modules\Interventions\Models\ProgramItem;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A row of a Programa that came from the results: a domain in Medio.
 *
 * @extends Factory<ProgramItem>
 */
final class ProgramItemFactory extends Factory
{
    protected $model = ProgramItem::class;

    /**
     * @return array<model-property<ProgramItem>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'program_id' => fn (array $attributes) => ProgramFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'scope' => ItemScope::Domain,
            'scope_key' => 'workload',
            'label' => 'Condiciones en el ambiente de trabajo',
            'category_key' => 'work-environment',
            'origin' => ItemOrigin::Results,
            'highest_level' => RiskLevel::Medium,
            'analysis' => null,
            'follow_up_due_on' => null,
            'follow_up_done_on' => null,
            'follow_up_result' => null,
            'follow_up_campaign_id' => null,
        ];
    }

    /** A row HR added: no level, no figures. */
    public function manual(): self
    {
        return $this->state(['origin' => ItemOrigin::Manual, 'highest_level' => null]);
    }
}
