<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Seating\Enums\BipedestationType;
use App\Modules\Seating\Models\StandingPosition;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A prolonged position (four hours on their feet) whose seat fits at the workstation (result A), with everything of
 * Quinta C filled in.
 *
 * @extends Factory<StandingPosition>
 */
final class StandingPositionFactory extends Factory
{
    protected $model = StandingPosition::class;

    /**
     * @return array<model-property<StandingPosition>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'name' => fake()->jobTitle(),
            'tasks' => 'Atiende de pie en el mostrador.',
            'bipedestation_type' => BipedestationType::Prolonged,
            'continuous_minutes' => 240,
            'can_work_seated' => true,
            'has_space_for_seat' => true,
            'needs_to_be_nearby' => false,
            'placement' => SeatPlacement::AtWorkstation,
            'figure_one_applies' => true,
            'max_people_at_once' => 2,
            'area_conditions' => ['noise' => 'Moderado', 'lighting' => 'Artificial', 'ventilation' => 'Natural', 'temperature' => 'Templada', 'vibration' => 'Ninguna', 'chemicals' => 'Ninguno'],
            'tools' => 'Lector de código de barras.',
            'physical_load' => 'Levantar bolsas ligeras.',
            'attention_level' => 'Visual',
            'default_standing_time' => 3,
            'default_posture_change' => 2,
            'default_surface' => 3,
            'default_movement_space' => 2,
            'default_pauses' => 2,
            'nature_allows' => null,
            'nature_reason' => null,
        ];
    }

    /** The seat does not fit at the workstation and the person must be near it (result B). */
    public function nearby(): self
    {
        return $this->state(['has_space_for_seat' => false, 'needs_to_be_nearby' => true, 'placement' => SeatPlacement::Nearby]);
    }

    /** The seat goes in a place determined within the center (result C). */
    public function withinCenter(): self
    {
        return $this->state(['can_work_seated' => false, 'needs_to_be_nearby' => false, 'placement' => SeatPlacement::WithinCenter]);
    }
}
