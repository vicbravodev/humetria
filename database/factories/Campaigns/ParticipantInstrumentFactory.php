<?php

declare(strict_types=1);

namespace Database\Factories\Campaigns;

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * A pending Guía I of a participant.
 *
 * @extends Factory<ParticipantInstrument>
 */
final class ParticipantInstrumentFactory extends Factory
{
    protected $model = ParticipantInstrument::class;

    /**
     * @return array<model-property<ParticipantInstrument>, mixed>
     */
    public function definition(): array
    {
        return [
            'participant_id' => ParticipantFactory::new(),
            'tenant_id' => fn (array $attributes) => $this->participant($attributes)->tenant_id,
            'instrument' => 'nom035.guide-i',
            'status' => InstrumentStatus::Pending,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function participant(array $attributes): Participant
    {
        $id = $attributes['participant_id'];

        return Participant::query()->withoutGlobalScopes()->findOrFail(is_string($id) ? $id : throw new InvalidArgumentException('A participant id is needed.'));
    }

    public function completed(): self
    {
        return $this->state(['status' => InstrumentStatus::Completed, 'completed_at' => now()]);
    }
}
