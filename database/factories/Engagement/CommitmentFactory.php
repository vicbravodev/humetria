<?php

declare(strict_types=1);

namespace Database\Factories\Engagement;

use App\Models\User;
use App\Modules\Engagement\Enums\CommitmentGroup;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A commitment for the whole company, on a closed survey of the same company.
 *
 * @extends Factory<Commitment>
 */
final class CommitmentFactory extends Factory
{
    protected $model = Commitment::class;

    /**
     * @return array<model-property<Commitment>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (): string => Survey::factory()->closed()->create()->tenant_id,
            'survey_id' => fn (array $attributes): string => Survey::factory()->closed()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'group_type' => CommitmentGroup::Company,
            'group_id' => null,
            'driver' => null,
            'heard' => 'Que las metas no se explican.',
            'will_do' => 'Presentar las metas en una junta por área.',
            'owner_name' => 'Ana Torres',
            'due_on' => now()->addMonth()->toDateString(),
            'status' => CommitmentStatus::Open,
            'created_by' => User::factory(),
        ];
    }
}
