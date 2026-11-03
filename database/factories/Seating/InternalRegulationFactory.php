<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Models\User;
use App\Modules\Seating\Enums\RegulationStatus;
use App\Modules\Seating\Models\InternalRegulation;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft with its four parts written.
 *
 * @extends Factory<InternalRegulation>
 */
final class InternalRegulationFactory extends Factory
{
    protected $model = InternalRegulation::class;

    /**
     * @return array<model-property<InternalRegulation>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'parts' => ['risk_factors' => 'Factores.', 'pauses' => 'Pausas.', 'seat_location' => 'Lugares.', 'usage_rule' => 'Norma.'],
            'status' => RegulationStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    /** Signed, deposited and distributed, with the file and the folio. */
    public function distributed(): self
    {
        return $this->state([
            'status' => RegulationStatus::Distributed,
            'signed_on' => now()->subMonths(3)->toDateString(),
            'deposited_on' => now()->subMonths(2)->toDateString(),
            'deposit_folio' => 'CFCRL-2026-0001',
            'distributed_on' => now()->subMonth()->toDateString(),
            'file_path' => 'regulations/rit.pdf',
        ]);
    }
}
