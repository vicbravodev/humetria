<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Enums\TrainingModality;
use App\Modules\Telework\Models\Training;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A remote training on hazard recognition, with no attendees yet.
 *
 * @extends Factory<Training>
 */
final class TrainingFactory extends Factory
{
    protected $model = Training::class;

    /**
     * @return array<model-property<Training>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'held_on' => '2026-02-20',
            'modality' => TrainingModality::Remote,
            'topics' => ['hazards'],
            'registered_by' => User::factory(),
        ];
    }
}
