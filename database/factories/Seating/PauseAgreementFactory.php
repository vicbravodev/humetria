<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Models\User;
use App\Modules\Seating\Models\PauseAgreement;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Three pauses of ten minutes agreed a month ago in a meeting.
 *
 * @extends Factory<PauseAgreement>
 */
final class PauseAgreementFactory extends Factory
{
    protected $model = PauseAgreement::class;

    /**
     * @return array<model-property<PauseAgreement>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'position_id' => fn (array $attributes) => StandingPositionFactory::new()->state(['tenant_id' => $attributes['tenant_id'], 'work_center_id' => $attributes['work_center_id']]),
            'pauses_per_shift' => 3,
            'minutes_each' => 10,
            'agreed_on' => now()->subMonth()->toDateString(),
            'how_agreed' => 'En la reunión mensual con las personas del puesto.',
            'evidence_path' => null,
            'created_by' => User::factory(),
        ];
    }
}
