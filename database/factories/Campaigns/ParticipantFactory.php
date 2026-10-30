<?php

declare(strict_types=1);

namespace Database\Factories\Campaigns;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * A person of the campaign's work center, invited to it.
 *
 * @extends Factory<Participant>
 */
final class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    /**
     * @return array<model-property<Participant>, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => CampaignFactory::new()->active(),
            'tenant_id' => fn (array $attributes) => $this->campaign($attributes)->tenant_id,
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state([
                'tenant_id' => $attributes['tenant_id'],
                'work_center_id' => $this->campaign($attributes)->work_center_id,
            ]),
            'invited_at' => now(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function campaign(array $attributes): Campaign
    {
        $id = $attributes['campaign_id'];

        return Campaign::query()->withoutGlobalScopes()->findOrFail(is_string($id) ? $id : throw new InvalidArgumentException('A campaign id is needed.'));
    }
}
