<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Models\User;
use App\Modules\Seating\Enums\MeasureKind;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Models\PreventiveMeasure;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A planned measure of active breaks for a position (the caller gives the position or the assessment).
 *
 * @extends Factory<PreventiveMeasure>
 */
final class PreventiveMeasureFactory extends Factory
{
    protected $model = PreventiveMeasure::class;

    /**
     * @return array<model-property<PreventiveMeasure>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'assessment_id' => null,
            'position_id' => fn (array $attributes) => StandingPositionFactory::new()->state(['tenant_id' => $attributes['tenant_id'], 'work_center_id' => $attributes['work_center_id']]),
            'kind' => MeasureKind::ActiveBreaks,
            'note' => null,
            'status' => MeasureStatus::Planned,
            'owner_user_id' => null,
            'due_on' => null,
            'evidence_path' => null,
            'proposed_by' => User::factory(),
        ];
    }
}
