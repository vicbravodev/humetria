<?php

declare(strict_types=1);

namespace Database\Factories\Interventions;

use App\Models\User;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Models\ProgressEntry;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A step of an action: it moved to "en curso".
 *
 * @extends Factory<ProgressEntry>
 */
final class ProgressEntryFactory extends Factory
{
    protected $model = ProgressEntry::class;

    /**
     * @return array<model-property<ProgressEntry>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'action_id' => fn (array $attributes) => ProgramActionFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'recorded_on' => '2026-11-20',
            'status' => ActionStatus::InProgress,
            'note' => null,
            'evidence_path' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
