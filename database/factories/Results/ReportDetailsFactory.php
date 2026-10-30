<?php

declare(strict_types=1);

namespace Database\Factories\Results;

use App\Models\User;
use App\Modules\Results\Models\ReportDetails;
use Database\Factories\Campaigns\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A filled-in report of a closed evaluation.
 *
 * @extends Factory<ReportDetails>
 */
final class ReportDetailsFactory extends Factory
{
    protected $model = ReportDetails::class;

    /**
     * @return array<model-property<ReportDetails>, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => CampaignFactory::new()->closed(),
            'tenant_id' => function (array $attributes): string {
                $value = DB::table('campaigns')->where('id', $attributes['campaign_id'])->value('tenant_id');

                return is_string($value) ? $value : throw new InvalidArgumentException('The campaign must exist.');
            },
            'objective' => 'Identificar los factores de riesgo psicosocial.',
            'activities' => 'Manufactura.',
            'conclusions' => 'Riesgo medio en la mayoría.',
            'recommendations' => 'Programa de intervención.',
            'responsible_name' => 'Dra. Laura Méndez',
            'responsible_license' => '12345678',
            'updated_by' => User::factory(),
        ];
    }
}
