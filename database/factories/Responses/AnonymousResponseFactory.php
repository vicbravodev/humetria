<?php

declare(strict_types=1);

namespace Database\Factories\Responses;

use App\Modules\Responses\Models\AnonymousResponse;
use Database\Factories\Campaigns\CampaignFactory;
use Database\Factories\Organization\AreaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A Guía II answered "Algunas veces" all through, in an active campaign.
 *
 * @extends Factory<AnonymousResponse>
 */
final class AnonymousResponseFactory extends Factory
{
    protected $model = AnonymousResponse::class;

    /**
     * @return array<model-property<AnonymousResponse>, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => CampaignFactory::new()->active(),
            'tenant_id' => fn (array $attributes) => $this->campaignColumn($attributes, 'tenant_id'),
            'work_center_id' => fn (array $attributes) => $this->campaignColumn($attributes, 'work_center_id'),
            'area_id' => fn (array $attributes) => AreaFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'instrument' => 'nom035.guide-ii',
            'instrument_version' => 'dof-2018-10-23',
            'answers' => array_fill_keys(array_map(strval(...), range(1, 40)), 'sometimes') + ['serves_clients' => 'no', 'supervises' => 'no'],
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
