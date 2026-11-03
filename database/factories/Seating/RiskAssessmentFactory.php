<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Models\User;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\RiskAssessment;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft that inherited a position's conditions and still misses its footwear and its Quinta C details. `signed()` is a
 * complete assessment signed a month ago with every condition at 1 point: total 7, Bajo.
 *
 * @extends Factory<RiskAssessment>
 */
final class RiskAssessmentFactory extends Factory
{
    protected $model = RiskAssessment::class;

    /**
     * @return array<model-property<RiskAssessment>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'position_id' => fn (array $attributes) => StandingPositionFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'scores' => ['standing_time' => 3, 'posture_change' => 2, 'surface' => 3, 'movement_space' => 2, 'discomfort' => 1, 'pauses' => 2],
            'status' => AssessmentStatus::Draft,
            'person_provided_health_data' => false,
            'reassessment_due' => false,
            'created_by' => User::factory(),
        ];
    }

    public function signed(): self
    {
        return $this->state(fn (): array => [
            'scores' => array_fill_keys(['standing_time', 'posture_change', 'surface', 'footwear', 'movement_space', 'discomfort', 'pauses'], 1),
            'total' => 7,
            'level' => RiskLevel::Low,
            'instrument_version' => 'dof-2025-07-17',
            'status' => AssessmentStatus::Signed,
            'verified_on' => now()->subMonth()->toDateString(),
            'other_aspects' => 'Ninguno.',
            'snapshot' => [
                'center_name' => 'Centro', 'center_address' => 'Calle 1', 'person_name' => 'Persona',
                'position_name' => 'Puesto', 'position_tasks' => 'Tareas', 'bipedestation' => 'Prolongada', 'continuous_minutes' => 240,
                'area_conditions' => ['noise' => 'Moderado'], 'tools' => 'Herramientas', 'physical_load' => 'Carga', 'attention_level' => 'Visual',
                'evaluator_name' => 'Evaluadora',
            ],
            'signed_by' => User::factory(),
            'signed_at' => now()->subMonth(),
        ]);
    }
}
