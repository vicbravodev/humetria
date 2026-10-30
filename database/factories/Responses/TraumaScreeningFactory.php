<?php

declare(strict_types=1);

namespace Database\Factories\Responses;

use App\Modules\Responses\Models\TraumaScreening;
use Database\Factories\Campaigns\CampaignFactory;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A Guía I with no event in section I, in an active campaign.
 *
 * @extends Factory<TraumaScreening>
 */
final class TraumaScreeningFactory extends Factory
{
    protected $model = TraumaScreening::class;

    /**
     * @return array<model-property<TraumaScreening>, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => CampaignFactory::new()->active(),
            'tenant_id' => fn (array $attributes) => $this->campaignColumn($attributes, 'tenant_id'),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state([
                'tenant_id' => $attributes['tenant_id'],
                'work_center_id' => $this->campaignColumn($attributes, 'work_center_id'),
            ]),
            'instrument_version' => 'dof-2018-10-23',
            'answers' => array_fill_keys(array_map(strval(...), range(1, 6)), 'no'),
            'requires_clinical_assessment' => false,
            'answered_at' => now(),
        ];
    }

    /**
     * A column of the campaign, read from its table: another module's model is not this module's to use.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    private function campaignColumn(array $attributes, string $column): string
    {
        $value = DB::table('campaigns')->where('id', $attributes['campaign_id'])->value($column);

        return is_string($value) ? $value : throw new InvalidArgumentException('The campaign must exist.');
    }
}
